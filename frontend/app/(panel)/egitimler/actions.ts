"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { z } from "zod";
import type { ActionResult, ActionState } from "@/lib/action-result";
import { ApiError, mutate } from "@/lib/api/client";
import {
  assignPersonnel,
  createEducation,
  deleteAssignment,
  deleteEducation,
  fetchPersonnelByDepartment,
  updateAssignmentStatus,
  updateEducation,
} from "@/lib/api/education";
import type { AssignResult } from "@/lib/api/types";
import { formToObject, validate } from "@/lib/validate";

const educationSchema = z.object({
  name: z.string().trim().min(1, "Eğitim adı zorunludur.").max(255, "En fazla 255 karakter olabilir."),
  type: z.string().min(1, "Eğitim türü seçin."),
  description: z
    .string()
    .trim()
    .max(5000, "En fazla 5000 karakter olabilir.")
    .optional()
    .transform((v) => (v ? v : undefined)),
});

function refresh() {
  revalidatePath("/", "layout");
}

export async function createEducationAction(_prev: ActionState, fd: FormData): Promise<ActionState> {
  const v = validate(educationSchema, formToObject(fd));
  if (!v.ok) return v.result;
  const result = await mutate(async () => {
    await createEducation(v.data);
    return "Eğitim oluşturuldu.";
  });
  if (result.ok) refresh();
  return result;
}

export async function updateEducationAction(id: number, _prev: ActionState, fd: FormData): Promise<ActionState> {
  const v = validate(educationSchema, formToObject(fd));
  if (!v.ok) return v.result;
  const result = await mutate(async () => {
    // description boş bırakıldıysa null göndererek temizle
    await updateEducation(id, { ...v.data, description: v.data.description ?? "" });
    return "Eğitim güncellendi.";
  });
  if (result.ok) refresh();
  return result;
}

export async function deleteEducationAction(id: number): Promise<ActionResult> {
  const result = await mutate(async () => {
    await deleteEducation(id);
    return "Eğitim silindi.";
  });
  if (result.ok) refresh();
  return result;
}

/** Detay sayfasından silme: başarılıysa listeye döner. */
export async function deleteEducationAndLeaveAction(id: number): Promise<ActionResult> {
  const result = await deleteEducationAction(id);
  if (result.ok) redirect("/egitimler");
  return result;
}

function assignMessage(r: AssignResult): string {
  return `${r.created.length} kişi atandı, ${r.already_assigned.length} kişi zaten atanmıştı, ${r.not_found.length} kişi bulunamadı.`;
}

const idsSchema = z.array(z.number().int().positive()).min(1, "En az bir personel seçin.");

export async function assignPersonnelAction(educationId: number, ids: number[]): Promise<ActionResult> {
  const v = validate(idsSchema, ids);
  if (!v.ok) return { ok: false, message: "En az bir personel seçin." };
  const result = await mutate(async () => assignMessage(await assignPersonnel(educationId, v.data)));
  if (result.ok) refresh();
  return result;
}

/** EP-2: departman bazında toplu atama. Id'ler Eğitim Portalı'nın /api/personnel?department_id uç noktasından alınır. */
export async function assignDepartmentAction(educationId: number, departmentId: number): Promise<ActionResult> {
  if (!Number.isInteger(departmentId) || departmentId <= 0) {
    return { ok: false, message: "Bir departman seçin." };
  }
  const result = await mutate(async () => {
    const list = await fetchPersonnelByDepartment(departmentId);
    if (list.meta.personnel_source === "unavailable") {
      throw new ApiError(503, "Personel Sistemi'ne ulaşılamıyor; atama yapılamaz.");
    }
    const ids = list.data.map((p) => p.id);
    if (ids.length === 0) return "Bu departmanda atanacak personel yok.";
    return assignMessage(await assignPersonnel(educationId, ids));
  });
  if (result.ok) refresh();
  return result;
}

const statusSchema = z.enum(["assigned", "in_progress", "completed"]);

export async function updateStatusAction(assignmentId: number, status: string): Promise<ActionResult> {
  const v = validate(statusSchema, status);
  if (!v.ok) return { ok: false, message: "Geçersiz durum." };
  const result = await mutate(async () => {
    await updateAssignmentStatus(assignmentId, v.data);
    return "Durum güncellendi.";
  });
  if (result.ok) refresh();
  return result;
}

export async function deleteAssignmentAction(assignmentId: number): Promise<ActionResult> {
  const result = await mutate(async () => {
    await deleteAssignment(assignmentId);
    return "Atama kaldırıldı.";
  });
  if (result.ok) refresh();
  return result;
}
