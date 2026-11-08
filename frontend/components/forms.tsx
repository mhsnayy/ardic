"use client";

import { createContext, useContext, useEffect, useRef, useState, useTransition } from "react";
import { useActionState } from "react";
import { toast } from "sonner";
import type { ActionResult, ActionState } from "@/lib/action-result";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";

const FormStateContext = createContext<ActionState>(null);

export const selectClass =
  "h-8 w-full rounded-lg border border-input bg-transparent px-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 aria-invalid:border-destructive dark:bg-input/30";

export function notify(result: ActionResult) {
  if (result.ok) toast.success(result.message);
  else toast.error(result.message);
}

function FieldError({ name }: { name: string }) {
  const state = useContext(FormStateContext);
  const msg = state && !state.ok ? state.fieldErrors?.[name]?.[0] : undefined;
  return msg ? <p className="text-sm text-destructive">{msg}</p> : null;
}

function useHasError(name: string) {
  const state = useContext(FormStateContext);
  return !!(state && !state.ok && state.fieldErrors?.[name]?.length);
}

export function Field({
  name,
  label,
  type = "text",
  defaultValue,
  required = true,
}: {
  name: string;
  label: string;
  type?: string;
  defaultValue?: string;
  required?: boolean;
}) {
  const invalid = useHasError(name);
  return (
    <div className="space-y-1.5">
      <Label htmlFor={name}>{label}</Label>
      <Input id={name} name={name} type={type} defaultValue={defaultValue} required={required} aria-invalid={invalid} />
      <FieldError name={name} />
    </div>
  );
}

export function TextareaField({
  name,
  label,
  defaultValue,
}: {
  name: string;
  label: string;
  defaultValue?: string;
}) {
  const invalid = useHasError(name);
  return (
    <div className="space-y-1.5">
      <Label htmlFor={name}>{label}</Label>
      <Textarea id={name} name={name} defaultValue={defaultValue} rows={4} aria-invalid={invalid} />
      <FieldError name={name} />
    </div>
  );
}

export function SelectField({
  name,
  label,
  options,
  defaultValue,
  placeholder = "Seçin",
}: {
  name: string;
  label: string;
  options: { value: string | number; label: string }[];
  defaultValue?: string | number;
  placeholder?: string;
}) {
  const invalid = useHasError(name);
  return (
    <div className="space-y-1.5">
      <Label htmlFor={name}>{label}</Label>
      <select
        id={name}
        name={name}
        required
        defaultValue={defaultValue === undefined ? "" : String(defaultValue)}
        className={selectClass}
        aria-invalid={invalid}
      >
        <option value="" disabled>
          {placeholder}
        </option>
        {options.map((o) => (
          <option key={o.value} value={o.value}>
            {o.label}
          </option>
        ))}
      </select>
      <FieldError name={name} />
    </div>
  );
}

/** Form: useActionState + toast. Alan değerlerini hata sonrası korumak için formAction manuel çağrılır. */
export function FormDialog({
  action,
  title,
  description,
  triggerLabel,
  triggerVariant = "default",
  submitLabel = "Kaydet",
  children,
}: {
  action: (prev: ActionState, fd: FormData) => Promise<ActionState>;
  title: string;
  description?: string;
  triggerLabel: string;
  triggerVariant?: "default" | "outline" | "ghost" | "secondary";
  submitLabel?: string;
  children: React.ReactNode;
}) {
  const [open, setOpen] = useState(false);
  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger render={<Button variant={triggerVariant} size={triggerVariant === "default" ? "default" : "sm"} />}>
        {triggerLabel}
      </DialogTrigger>
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          {description && <DialogDescription>{description}</DialogDescription>}
        </DialogHeader>
        <InnerForm action={action} submitLabel={submitLabel} onDone={() => setOpen(false)}>
          {children}
        </InnerForm>
      </DialogContent>
    </Dialog>
  );
}

function InnerForm({
  action,
  submitLabel,
  onDone,
  children,
}: {
  action: (prev: ActionState, fd: FormData) => Promise<ActionState>;
  submitLabel: string;
  onDone: () => void;
  children: React.ReactNode;
}) {
  const [state, formAction, pending] = useActionState(action, null);
  const [, startTransition] = useTransition();
  const handled = useRef<ActionState>(null);

  useEffect(() => {
    if (!state || handled.current === state) return;
    handled.current = state;
    notify(state);
    if (state.ok) onDone();
  }, [state, onDone]);

  return (
    <FormStateContext.Provider value={state}>
      <form
        className="space-y-4"
        onSubmit={(e) => {
          e.preventDefault();
          const fd = new FormData(e.currentTarget);
          startTransition(() => formAction(fd));
        }}
      >
        {children}
        <div className="flex justify-end gap-2 pt-2">
          <Button type="submit" disabled={pending}>
            {pending ? "Kaydediliyor..." : submitLabel}
          </Button>
        </div>
      </form>
    </FormStateContext.Provider>
  );
}

/** Onay dialog'lu silme / kaldırma butonu. */
export function ConfirmButton({
  action,
  label,
  title,
  description,
  confirmLabel = "Sil",
}: {
  action: () => Promise<ActionResult>;
  label: string;
  title: string;
  description: string;
  confirmLabel?: string;
}) {
  const [open, setOpen] = useState(false);
  const [pending, startTransition] = useTransition();

  return (
    <>
      <Button variant="destructive" size="sm" onClick={() => setOpen(true)}>
        {label}
      </Button>
      <AlertDialog open={open} onOpenChange={setOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{title}</AlertDialogTitle>
            <AlertDialogDescription>{description}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Vazgeç</AlertDialogCancel>
            <AlertDialogAction
              disabled={pending}
              onClick={() => {
                startTransition(async () => {
                  const result = await action();
                  notify(result);
                  setOpen(false);
                });
              }}
            >
              {pending ? "İşleniyor..." : confirmLabel}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
