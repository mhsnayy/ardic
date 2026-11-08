"use client";

import { useMemo, useState, useTransition } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import type { ActionResult } from "@/lib/action-result";
import { notify, selectClass } from "@/components/forms";
import { cn } from "@/lib/utils";

type Person = { id: number; name: string; email: string; departmentName: string; assigned: boolean };
type Dept = { id: number; name: string };

export function AssignDialog({
  people,
  departments,
  disabled,
  assignPeople,
  assignDepartment,
}: {
  people: Person[];
  departments: Dept[];
  disabled: boolean;
  assignPeople: (ids: number[]) => Promise<ActionResult>;
  assignDepartment: (departmentId: number) => Promise<ActionResult>;
}) {
  const [open, setOpen] = useState(false);
  const [mode, setMode] = useState<"people" | "department">("people");
  const [query, setQuery] = useState("");
  const [selected, setSelected] = useState<Set<number>>(new Set());
  const [departmentId, setDepartmentId] = useState("");
  const [pending, startTransition] = useTransition();

  const filtered = useMemo(() => {
    const q = query.trim().toLocaleLowerCase("tr-TR");
    if (!q) return people;
    return people.filter((p) => `${p.name} ${p.email}`.toLocaleLowerCase("tr-TR").includes(q));
  }, [people, query]);

  function toggle(id: number) {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }

  function submit() {
    startTransition(async () => {
      const result =
        mode === "people" ? await assignPeople([...selected]) : await assignDepartment(Number(departmentId));
      notify(result);
      if (result.ok) {
        setOpen(false);
        setSelected(new Set());
        setDepartmentId("");
      }
    });
  }

  const canSubmit = mode === "people" ? selected.size > 0 : departmentId !== "";

  return (
    <>
      <Button disabled={disabled} onClick={() => setOpen(true)}>
        Personel ata
      </Button>
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>Personel ata</DialogTitle>
            <DialogDescription>Kişileri tek tek seçin ya da bir departmanın tüm personelini atayın.</DialogDescription>
          </DialogHeader>

          <div className="grid grid-cols-2 gap-1 rounded-lg bg-muted p-1">
            {(
              [
                ["people", "Kişi seç"],
                ["department", "Departman seç"],
              ] as const
            ).map(([m, label]) => (
              <button
                key={m}
                type="button"
                onClick={() => setMode(m)}
                className={cn(
                  "rounded-md px-3 py-1.5 text-sm font-medium",
                  mode === m ? "bg-background shadow-sm" : "text-muted-foreground",
                )}
              >
                {label}
              </button>
            ))}
          </div>

          {mode === "people" ? (
            <div className="space-y-3">
              <Input
                type="search"
                placeholder="Ad veya e-posta ara..."
                value={query}
                onChange={(e) => setQuery(e.target.value)}
              />
              <ul className="max-h-64 divide-y overflow-y-auto rounded-lg border">
                {filtered.length === 0 && (
                  <li className="p-4 text-center text-sm text-muted-foreground">Personel bulunamadı.</li>
                )}
                {filtered.map((p) => (
                  <li key={p.id}>
                    <label
                      className={cn(
                        "flex cursor-pointer items-center gap-3 p-2.5 text-sm hover:bg-muted/50",
                        p.assigned && "cursor-not-allowed opacity-50",
                      )}
                    >
                      <input
                        type="checkbox"
                        className="size-4"
                        disabled={p.assigned}
                        checked={selected.has(p.id)}
                        onChange={() => toggle(p.id)}
                      />
                      <span className="min-w-0 flex-1">
                        <span className="block truncate font-medium">{p.name}</span>
                        <span className="block truncate text-xs text-muted-foreground">
                          {p.email} · {p.departmentName}
                        </span>
                      </span>
                      {p.assigned && <span className="text-xs text-muted-foreground">Atanmış</span>}
                    </label>
                  </li>
                ))}
              </ul>
              <p className="text-sm text-muted-foreground">{selected.size} kişi seçildi</p>
            </div>
          ) : (
            <div className="space-y-1.5">
              <Label htmlFor="assign-dept">Departman</Label>
              <select
                id="assign-dept"
                value={departmentId}
                onChange={(e) => setDepartmentId(e.target.value)}
                className={selectClass}
              >
                <option value="">Departman seçin</option>
                {departments.map((d) => (
                  <option key={d.id} value={d.id}>
                    {d.name}
                  </option>
                ))}
              </select>
              <p className="text-sm text-muted-foreground">
                Seçilen departmandaki tüm personel atanır. Zaten atanmış olanlar atlanır.
              </p>
            </div>
          )}

          <div className="flex justify-end gap-2">
            <Button variant="outline" onClick={() => setOpen(false)}>
              Vazgeç
            </Button>
            <Button disabled={!canSubmit || pending} onClick={submit}>
              {pending ? "Atanıyor..." : "Ata"}
            </Button>
          </div>
        </DialogContent>
      </Dialog>
    </>
  );
}
