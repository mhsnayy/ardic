"use client";

import { useTransition } from "react";
import type { ActionResult } from "@/lib/action-result";
import type { AssignmentStatus } from "@/lib/api/types";
import { notify, selectClass } from "@/components/forms";
import { STATUS_LABELS } from "@/lib/format";
import { cn } from "@/lib/utils";

export function StatusSelect({
  value,
  action,
}: {
  value: AssignmentStatus;
  action: (status: string) => Promise<ActionResult>;
}) {
  const [pending, startTransition] = useTransition();
  return (
    <select
      aria-label="Durum"
      value={value}
      disabled={pending}
      className={cn(selectClass, "w-36")}
      onChange={(e) => {
        const next = e.target.value;
        startTransition(async () => notify(await action(next)));
      }}
    >
      {(Object.keys(STATUS_LABELS) as AssignmentStatus[]).map((s) => (
        <option key={s} value={s}>
          {STATUS_LABELS[s]}
        </option>
      ))}
    </select>
  );
}
