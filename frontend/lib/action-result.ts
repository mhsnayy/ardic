export type FieldErrors = Record<string, string[]>;

export type ActionResult =
  | { ok: true; message: string }
  | { ok: false; message: string; fieldErrors?: FieldErrors; values?: Record<string, string> };

export type ActionState = ActionResult | null;
