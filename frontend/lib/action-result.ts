export type FieldErrors = Record<string, string[]>;

export type ActionResult =
  | { ok: true; message: string }
  | { ok: false; message: string; fieldErrors?: FieldErrors };

export type ActionState = ActionResult | null;
