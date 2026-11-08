"use client";

import { useState, useTransition } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
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
import { notify } from "@/components/forms";
import { toast } from "sonner";
import type { ActionResult } from "@/lib/action-result";
import { formatDay } from "@/lib/format";
import type { CreateLinkResult } from "../link-actions";

type Confirm = "regenerate" | "revoke" | null;

export function LearningLinkDialog({
  personnelName,
  expiresAt,
  create,
  revoke,
}: {
  personnelName: string;
  expiresAt: string | null;
  create: () => Promise<CreateLinkResult>;
  revoke: () => Promise<ActionResult>;
}) {
  const [open, setOpen] = useState(false);
  const [url, setUrl] = useState<string | null>(null);
  const [confirm, setConfirm] = useState<Confirm>(null);
  const [pending, startTransition] = useTransition();
  const active = expiresAt !== null;

  function changeOpen(next: boolean) {
    setOpen(next);
    if (!next) setUrl(null); // token bir daha gösterilmez
  }

  function doCreate() {
    startTransition(async () => {
      const r = await create();
      notify(r);
      if (r.ok) setUrl(r.link.url);
      setConfirm(null);
    });
  }

  function doRevoke() {
    startTransition(async () => {
      const r = await revoke();
      notify(r);
      setConfirm(null);
      if (r.ok) changeOpen(false);
    });
  }

  async function copy() {
    if (!url) return;
    try {
      await navigator.clipboard.writeText(url);
      toast.success("Link kopyalandı.");
    } catch {
      toast.error("Kopyalanamadı. Linki elle seçip kopyalayın.");
    }
  }

  return (
    <>
      <Button variant="outline" size="sm" onClick={() => setOpen(true)}>
        Öğrenme linki
      </Button>
      <Dialog open={open} onOpenChange={changeOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Öğrenme linki</DialogTitle>
            <DialogDescription>{personnelName} için giriş gerektirmeyen eğitim linki.</DialogDescription>
          </DialogHeader>

          {url ? (
            <div className="space-y-3">
              <div className="flex gap-2">
                <Input readOnly value={url} onFocus={(e) => e.currentTarget.select()} aria-label="Öğrenme linki" />
                <Button type="button" onClick={copy}>
                  Kopyala
                </Button>
              </div>
              <p className="text-sm text-amber-700 dark:text-amber-300">
                Bu link yalnızca şimdi gösterilir, personele iletin.
              </p>
              <p className="text-sm text-muted-foreground">Bitiş tarihi: {formatDay(expiresAt)}</p>
            </div>
          ) : active ? (
            <div className="space-y-3">
              <p className="text-sm">
                Aktif bir link var. Bitiş tarihi: <span className="font-medium">{formatDay(expiresAt)}</span>
              </p>
              <p className="text-sm text-muted-foreground">
                Güvenlik nedeniyle mevcut linkin adresi tekrar gösterilemez.
              </p>
              <div className="flex flex-wrap justify-end gap-2">
                <Button variant="outline" disabled={pending} onClick={() => setConfirm("regenerate")}>
                  Yeni link oluştur
                </Button>
                <Button variant="destructive" disabled={pending} onClick={() => setConfirm("revoke")}>
                  Linki iptal et
                </Button>
              </div>
            </div>
          ) : (
            <div className="space-y-3">
              <p className="text-sm text-muted-foreground">Bu atama için aktif bir link yok.</p>
              <div className="flex justify-end">
                <Button disabled={pending} onClick={doCreate}>
                  {pending ? "Oluşturuluyor..." : "Link oluştur"}
                </Button>
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>

      <AlertDialog open={confirm !== null} onOpenChange={(o) => !o && setConfirm(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{confirm === "revoke" ? "Link iptal edilsin mi?" : "Yeni link oluşturulsun mu?"}</AlertDialogTitle>
            <AlertDialogDescription>
              {confirm === "revoke"
                ? "Mevcut link hemen geçersiz olur."
                : "Eski link geçersiz olur ve yeni bir link üretilir."}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Vazgeç</AlertDialogCancel>
            <AlertDialogAction disabled={pending} onClick={confirm === "revoke" ? doRevoke : doCreate}>
              {pending ? "İşleniyor..." : confirm === "revoke" ? "İptal et" : "Oluştur"}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
