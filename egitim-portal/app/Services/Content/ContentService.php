<?php

namespace App\Services\Content;

use App\Models\Education;
use App\Models\EducationContent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ContentService
{
    public function __construct(private ContentCreatorRegistry $creators) {}

    /** @param array<string, mixed> $data validated input */
    public function add(Education $education, array $data): EducationContent
    {
        return DB::transaction(function () use ($education, $data) {
            $contentable = $this->creators->for($data['type'])->create($data, $education);

            $content = new EducationContent([
                'title' => $data['title'],
                'is_required' => $data['is_required'] ?? true,
                'sort_order' => ((int) $education->contents()->max('sort_order')) + 1,
            ]);
            $content->education()->associate($education);
            $content->contentable()->associate($contentable);
            $content->save();

            return $content->load('contentable');
        });
    }

    /** @param list<int> $ids */
    public function reorder(Education $education, array $ids): Collection
    {
        DB::transaction(function () use ($education, $ids) {
            foreach (array_values($ids) as $i => $id) {
                $education->contents()->whereKey($id)->update(['sort_order' => $i + 1]);
            }
        });

        return $education->contents()->with('contentable')->get();
    }

    public function remove(EducationContent $content): void
    {
        DB::transaction(function () use ($content) {
            $education = $content->education;
            $content->delete();
            $education->contents()->pluck('id')->each(
                fn ($id, $i) => EducationContent::whereKey($id)->update(['sort_order' => $i + 1])
            );
        });
    }
}
