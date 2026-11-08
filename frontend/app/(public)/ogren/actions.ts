"use server";

import { z } from "zod";
import { reportProgress, tokenSchema } from "@/lib/api/learn";
import type { ProgressResult } from "@/lib/api/types";
import { parseId } from "@/lib/validate";

export type ProgressActionResult =
  | { ok: true; data: ProgressResult }
  | { ok: false; invalid?: boolean; message: string };

const bodySchema = z.object({
  position: z.number().finite().min(0).max(1e6).optional(),
  // Eski istemciler için kabul edilir; sunucu süreyi artık kullanmaz.
  duration: z.number().finite().positive().max(1e6).optional(),
});

/** Öğrenme sayfasından ilerleme bildirimi. Cookie kullanılmaz; token istemciden gelir ve doğrulanır. */
export async function reportProgressAction(
  token: string,
  rawContentId: number,
  body: { position?: number; duration?: number },
): Promise<ProgressActionResult> {
  const t = tokenSchema.safeParse(token);
  const contentId = parseId(rawContentId);
  const b = bodySchema.safeParse(body);
  if (!t.success) return { ok: false, invalid: true, message: "Link geçersiz." };
  if (contentId === null || !b.success) return { ok: false, message: "Geçersiz istek." };
  const r = await reportProgress(t.data, contentId, b.data);
  if (r.status === "ok") return { ok: true, data: r.data };
  if (r.status === "invalid") return { ok: false, invalid: true, message: "Link geçersiz." };
  return { ok: false, message: r.message };
}
