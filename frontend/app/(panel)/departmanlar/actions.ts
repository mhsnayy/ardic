"use server";

import { revalidatePath } from "next/cache";
import { z } from "zod";
import type { ActionResult, ActionState } from "@/lib/action-result";
import { mutate } from "@/lib/api/client";
import { createDepartment, deleteDepartment, updateDepartment } from "@/lib/api/personnel";
import { INVALID_ID, formToObject, parseId, validate } from "@/lib/validate";

const schema = z.object({
  name: z.string().trim().min(1, "Departman adı zorunludur.").max(100, "En fazla 100 karakter olabilir."),
});

function refresh() {
  revalidatePath("/", "layout");
}

export async function createDepartmentAction(_prev: ActionState, fd: FormData): Promise<ActionState> {
  const v = validate(schema, formToObject(fd));
  if (!v.ok) return v.result;
  const result = await mutate(async () => {
    await createDepartment(v.data.name);
    return "Departman eklendi.";
  });
  if (result.ok) refresh();
  return result;
}

export async function updateDepartmentAction(raw_id: number, _prev: ActionState, fd: FormData): Promise<ActionState> {
  const id = parseId(raw_id);
  if (id === null) return INVALID_ID;
  const v = validate(schema, formToObject(fd));
  if (!v.ok) return v.result;
  const result = await mutate(async () => {
    await updateDepartment(id, v.data.name);
    return "Departman güncellendi.";
  });
  if (result.ok) refresh();
  return result;
}

/** BR-2: personeli olan departman silinemez (409 mesajı toast olarak gösterilir). */
export async function deleteDepartmentAction(raw_id: number): Promise<ActionResult> {
  const id = parseId(raw_id);
  if (id === null) return INVALID_ID;
  const result = await mutate(async () => {
    await deleteDepartment(id);
    return "Departman silindi.";
  });
  if (result.ok) refresh();
  return result;
}
