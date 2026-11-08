"use server";

import { revalidatePath } from "next/cache";
import { z } from "zod";
import type { ActionResult, FieldErrors } from "@/lib/action-result";
import { mutate } from "@/lib/api/client";
import { createContent, deleteContent, reorderContents, requestUpload } from "@/lib/api/education";
import type { UploadTarget } from "@/lib/api/types";
import { INVALID_ID, parseId, validate } from "@/lib/validate";

// Sınırlar backend ile aynıdır (asıl doğrulama backend'de); burada erken geri bildirim içindir.
const MB = 1024 * 1024;
const uploadSchema = z
  .object({
    kind: z.enum(["video", "document"]),
    filename: z.string().trim().min(1, "Dosya seçin.").max(255),
    mime_type: z.string().min(1, "Dosya türü belirlenemedi."),
    size_bytes: z.number().int().positive("Dosya boş olamaz."),
  })
  .superRefine((v, ctx) => {
    const allowed = v.kind === "video" ? ["video/mp4", "video/webm"] : ["application/pdf"];
    const max = (v.kind === "video" ? 200 : 50) * MB;
    if (!allowed.includes(v.mime_type)) {
      ctx.addIssue({
        code: "custom",
        path: ["file"],
        message: v.kind === "video" ? "Sadece MP4 veya WebM yüklenebilir." : "Sadece PDF yüklenebilir.",
      });
    }
    if (v.size_bytes > max) {
      ctx.addIssue({ code: "custom", path: ["file"], message: `Dosya en fazla ${max / MB} MB olabilir.` });
    }
  });

export type UploadTargetResult =
  | { ok: true; target: UploadTarget }
  | { ok: false; message: string; fieldErrors?: FieldErrors };

/** Adım 1: yükleme hedefi iste. Dönen imzalı URL tarayıcıya iner (mimari §1 istisnası). */
export async function requestUploadAction(
  raw_educationId: number,
  input: { kind: string; filename: string; mime_type: string; size_bytes: number },
): Promise<UploadTargetResult> {
  const educationId = parseId(raw_educationId);
  if (educationId === null) return { ok: false, message: INVALID_ID.message };
  const v = validate(uploadSchema, input);
  if (!v.ok) return v.result as UploadTargetResult;
  const holder: { target?: UploadTarget } = {};
  const result = await mutate(async () => {
    holder.target = await requestUpload(educationId, v.data);
    return "ok";
  });
  if (!result.ok || !holder.target) {
    const fe: FieldErrors = { ...(result.ok ? {} : (result.fieldErrors ?? {})) };
    const file = [...(fe.mime_type ?? []), ...(fe.size_bytes ?? [])];
    if (file.length) fe.file = file;
    return { ok: false, message: result.ok ? "Yükleme hedefi alınamadı." : result.message, fieldErrors: fe };
  }
  return { ok: true, target: holder.target };
}

const baseContent = {
  title: z.string().trim().min(1, "Başlık zorunludur.").max(255, "En fazla 255 karakter olabilir."),
  is_required: z.boolean(),
};
const contentSchema = z.discriminatedUnion("type", [
  z.object({
    type: z.enum(["video", "document"]),
    ...baseContent,
    upload_id: z.union([z.number().int().positive(), z.string().min(1)]),
  }),
  z.object({
    type: z.literal("link"),
    ...baseContent,
    url: z
      .string()
      .trim()
      .min(1, "Bağlantı adresi zorunludur.")
      .max(2048)
      .refine((u) => /^https?:\/\/\S+$/i.test(u), "Geçerli bir http(s) adresi girin."),
  }),
]);

/** Adım 3: içeriği oluştur (video/PDF için upload_id ile). */
export async function createContentAction(
  raw_educationId: number,
  input: { type: string; title: string; is_required: boolean; upload_id?: number | string; url?: string },
): Promise<ActionResult> {
  const educationId = parseId(raw_educationId);
  if (educationId === null) return INVALID_ID;
  const v = validate(contentSchema, input);
  if (!v.ok) return v.result;
  const result = await mutate(async () => {
    await createContent(educationId, v.data);
    return "İçerik eklendi.";
  });
  if (result.ok) revalidatePath("/", "layout");
  return result;
}

export async function reorderContentsAction(raw_educationId: number, ids: number[]): Promise<ActionResult> {
  const educationId = parseId(raw_educationId);
  if (educationId === null) return INVALID_ID;
  const v = validate(z.array(z.number().int().positive()).min(1), ids);
  if (!v.ok) return INVALID_ID;
  const result = await mutate(async () => {
    await reorderContents(educationId, v.data);
    return "Sıralama güncellendi.";
  });
  if (result.ok) revalidatePath("/", "layout");
  return result;
}

export async function deleteContentAction(raw_id: number): Promise<ActionResult> {
  const id = parseId(raw_id);
  if (id === null) return INVALID_ID;
  const result = await mutate(async () => {
    await deleteContent(id);
    return "İçerik silindi.";
  });
  if (result.ok) revalidatePath("/", "layout");
  return result;
}
