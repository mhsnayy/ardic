"use server";

import { revalidatePath } from "next/cache";
import { z } from "zod";
import type { ActionResult, ActionState } from "@/lib/action-result";
import { mutate } from "@/lib/api/client";
import { createPersonnel, deletePersonnel, updatePersonnel } from "@/lib/api/personnel";
import { formToObject, validate } from "@/lib/validate";

const schema = z.object({
  first_name: z.string().trim().min(1, "Ad zorunludur.").max(100, "Ad en fazla 100 karakter olabilir."),
  last_name: z.string().trim().min(1, "Soyad zorunludur.").max(100, "Soyad en fazla 100 karakter olabilir."),
  email: z.string().trim().min(1, "E-posta zorunludur.").email("Geçerli bir e-posta girin."),
  department_id: z.coerce.number({ message: "Departman seçin." }).int().positive("Departman seçin."),
});

function refresh() {
  revalidatePath("/", "layout");
}

export async function createPersonnelAction(_prev: ActionState, fd: FormData): Promise<ActionState> {
  const v = validate(schema, formToObject(fd));
  if (!v.ok) return v.result;
  const result = await mutate(async () => {
    await createPersonnel(v.data);
    return "Personel eklendi.";
  });
  if (result.ok) refresh();
  return result;
}

export async function updatePersonnelAction(id: number, _prev: ActionState, fd: FormData): Promise<ActionState> {
  const v = validate(schema, formToObject(fd));
  if (!v.ok) return v.result;
  const result = await mutate(async () => {
    await updatePersonnel(id, v.data);
    return "Personel güncellendi.";
  });
  if (result.ok) refresh();
  return result;
}

export async function deletePersonnelAction(id: number): Promise<ActionResult> {
  const result = await mutate(async () => {
    await deletePersonnel(id);
    return "Personel silindi.";
  });
  if (result.ok) refresh();
  return result;
}
