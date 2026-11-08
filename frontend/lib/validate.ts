import { z } from "zod";
import type { ActionResult } from "./action-result";

export function validate<S extends z.ZodType>(
  schema: S,
  data: unknown,
): { ok: true; data: z.infer<S> } | { ok: false; result: ActionResult } {
  const parsed = schema.safeParse(data);
  if (parsed.success) return { ok: true, data: parsed.data };
  const fieldErrors = z.flattenError(parsed.error).fieldErrors as Record<string, string[]>;
  return {
    ok: false,
    result: { ok: false, message: "Lütfen formdaki hataları düzeltin.", fieldErrors },
  };
}

export function formToObject(fd: FormData): Record<string, FormDataEntryValue> {
  return Object.fromEntries(fd.entries());
}
