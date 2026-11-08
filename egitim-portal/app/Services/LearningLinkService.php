<?php

namespace App\Services;

use App\Models\EducationAssignment;
use App\Models\LearningLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LearningLinkService
{
    public const TOKEN_LENGTH = 48;

    /**
     * Issues a new link and revokes all previous active ones (BR-9).
     * The plain token is returned once; only its sha256 hash is stored.
     *
     * @return array{token: string, url: string, link: LearningLink}
     */
    public function issue(EducationAssignment $assignment): array
    {
        return DB::transaction(function () use ($assignment) {
            // Serialise concurrent issues for the same assignment (BR-9: one active link).
            EducationAssignment::whereKey($assignment->id)->lockForUpdate()->first();
            $this->revoke($assignment);

            $token = Str::random(self::TOKEN_LENGTH); // [A-Za-z0-9], CSPRNG
            $link = LearningLink::create([
                'assignment_id' => $assignment->id,
                'token_hash' => LearningLink::hashToken($token),
                'expires_at' => now()->addDays((int) config('portal.learning_link_days')),
            ]);

            return [
                'token' => $token,
                'url' => rtrim(config('portal.frontend_url'), '/').'/ogren/'.$token,
                'link' => $link,
            ];
        });
    }

    public function revoke(EducationAssignment $assignment): void
    {
        LearningLink::where('assignment_id', $assignment->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    /** Resolves a plain token to its active link; null for anything invalid. */
    public function resolve(?string $token): ?LearningLink
    {
        if ($token === null || ! preg_match('/^[A-Za-z0-9]{'.self::TOKEN_LENGTH.'}$/', $token)) {
            return null;
        }

        return LearningLink::active()->with('assignment')
            ->where('token_hash', LearningLink::hashToken($token))
            ->first();
    }
}
