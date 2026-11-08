"use client";

import { useTransition } from "react";
import { ArrowDown, ArrowUp } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ConfirmButton, notify } from "@/components/forms";
import type { ActionResult } from "@/lib/action-result";
import type { Content } from "@/lib/api/types";
import { contentKinds } from "./content-kinds";

export function ContentList({
  contents,
  reorder,
  remove,
}: {
  contents: Content[];
  reorder: (ids: number[]) => Promise<ActionResult>;
  remove: (id: number) => Promise<ActionResult>;
}) {
  const [pending, startTransition] = useTransition();

  function move(index: number, delta: -1 | 1) {
    const ids = contents.map((c) => c.id);
    const target = index + delta;
    if (target < 0 || target >= ids.length) return;
    [ids[index], ids[target]] = [ids[target], ids[index]];
    startTransition(async () => notify(await reorder(ids)));
  }

  if (contents.length === 0) {
    return (
      <div className="rounded-lg border bg-card py-8 text-center text-sm text-muted-foreground">
        Bu eğitime henüz içerik eklenmedi.
      </div>
    );
  }

  return (
    <ul className="divide-y rounded-lg border bg-card">
      {contents.map((c, i) => {
        const { label, icon: Icon, Details } = contentKinds[c.kind];
        return (
          <li key={c.id} className="flex flex-wrap items-center gap-3 p-3">
            <span className="w-5 text-center text-sm text-muted-foreground">{i + 1}</span>
            <Icon className="size-5 shrink-0 text-muted-foreground" aria-hidden />
            <div className="min-w-0 flex-1 basis-48">
              <div className="flex flex-wrap items-center gap-2">
                <span className="font-medium break-words">{c.title}</span>
                <Badge variant="outline">{label}</Badge>
                <Badge variant={c.is_required ? "default" : "secondary"}>
                  {c.is_required ? "Zorunlu" : "Opsiyonel"}
                </Badge>
              </div>
              <p className="mt-0.5 text-sm text-muted-foreground">
                <Details content={c} />
              </p>
            </div>
            <div className="ml-auto flex items-center gap-1.5">
              <Button
                variant="outline"
                size="sm"
                aria-label="Yukarı taşı"
                disabled={pending || i === 0}
                onClick={() => move(i, -1)}
              >
                <ArrowUp />
              </Button>
              <Button
                variant="outline"
                size="sm"
                aria-label="Aşağı taşı"
                disabled={pending || i === contents.length - 1}
                onClick={() => move(i, 1)}
              >
                <ArrowDown />
              </Button>
              <ConfirmButton
                action={remove.bind(null, c.id)}
                label="Sil"
                title="İçerik silinsin mi?"
                description={`"${c.title}" içeriği silinecek.`}
              />
            </div>
          </li>
        );
      })}
    </ul>
  );
}
