"use server";

import { revalidatePath } from "next/cache";
import type { ActionResult } from "@/lib/action-result";
import { mutate } from "@/lib/api/client";
import { createLearningLink, revokeLearningLink } from "@/lib/api/education";
import type { LearningLink } from "@/lib/api/types";
import { INVALID_ID, parseId } from "@/lib/validate";

export type CreateLinkResult =
  | { ok: true; message: string; link: LearningLink }
  | { ok: false; message: string };

/** Yeni öğrenme linki üretir (eskisi geçersiz olur, BR-9). Token yalnızca bu yanıtta döner. */
export async function createLearningLinkAction(raw_assignmentId: number): Promise<CreateLinkResult> {
  const id = parseId(raw_assignmentId);
  if (id === null) return { ok: false, message: INVALID_ID.message };
  const holder: { link?: LearningLink } = {};
  const result = await mutate(async () => {
    holder.link = await createLearningLink(id);
    return "Öğrenme linki oluşturuldu.";
  });
  if (!result.ok || !holder.link) return { ok: false, message: result.ok ? "Link oluşturulamadı." : result.message };
  revalidatePath("/", "layout");
  return { ok: true, message: result.message, link: holder.link };
}

export async function revokeLearningLinkAction(raw_assignmentId: number): Promise<ActionResult> {
  const id = parseId(raw_assignmentId);
  if (id === null) return INVALID_ID;
  const result = await mutate(async () => {
    await revokeLearningLink(id);
    return "Öğrenme linki iptal edildi.";
  });
  if (result.ok) revalidatePath("/", "layout");
  return result;
}
