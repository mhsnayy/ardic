"use client";

import { useRef, useState } from "react";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Progress, ProgressLabel, ProgressValue } from "@/components/ui/progress";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { notify, selectClass } from "@/components/forms";
import type { ActionResult, FieldErrors } from "@/lib/action-result";
import type { ContentKind } from "@/lib/api/types";
import { formatBytes, formatDuration } from "@/lib/format";
import type { UploadTargetResult } from "../content-actions";
import { contentKinds } from "./content-kinds";

type Props = {
  requestUpload: (input: {
    kind: string;
    filename: string;
    mime_type: string;
    size_bytes: number;
  }) => Promise<UploadTargetResult>;
  create: (input: {
    type: string;
    title: string;
    is_required: boolean;
    upload_id?: number | string;
    url?: string;
    duration_seconds?: number;
  }) => Promise<ActionResult>;
};

const DURATION_ERROR = "Video süresi okunamadı. Lütfen MP4 ya da süre bilgisi içeren bir WebM yükleyin.";

/** Yüklemeden önce, tarayıcıda video süresini (sn) okur. Okunamazsa null. */
function readVideoDuration(file: File): Promise<number | null> {
  return new Promise((resolve) => {
    const objectUrl = URL.createObjectURL(file);
    const video = document.createElement("video");
    let done = false;
    const finish = (value: number | null) => {
      if (done) return;
      done = true;
      clearTimeout(timer);
      video.removeAttribute("src");
      video.load();
      URL.revokeObjectURL(objectUrl);
      resolve(value);
    };
    const timer = setTimeout(() => finish(null), 10000);
    video.preload = "metadata";
    video.muted = true;
    video.onloadedmetadata = () => {
      const d = video.duration;
      finish(Number.isFinite(d) && d > 0 ? d : null);
    };
    video.onerror = () => finish(null);
    video.src = objectUrl;
  });
}

type UploadResult = { ok: true } | { ok: false; aborted: boolean; message: string };

/** Tarayıcıdan, backend'in verdiği imzalı hedefe doğrudan yükleme (mimari §1 istisnası). */
function putFile(
  target: { upload_url: string; method: string; headers: Record<string, string> },
  file: File,
  xhrRef: { current: XMLHttpRequest | null },
  onProgress: (pct: number) => void,
): Promise<UploadResult> {
  return new Promise((resolve) => {
    const xhr = new XMLHttpRequest();
    xhrRef.current = xhr;
    xhr.open(target.method || "PUT", target.upload_url);
    for (const [k, v] of Object.entries(target.headers ?? {})) xhr.setRequestHeader(k, v);
    xhr.upload.onprogress = (e) => {
      if (e.lengthComputable) onProgress(Math.round((e.loaded / e.total) * 100));
    };
    xhr.onload = () => {
      xhrRef.current = null;
      if (xhr.status >= 200 && xhr.status < 300) resolve({ ok: true });
      else resolve({ ok: false, aborted: false, message: `Yükleme başarısız oldu (${xhr.status}).` });
    };
    xhr.onerror = () => {
      xhrRef.current = null;
      resolve({ ok: false, aborted: false, message: "Yükleme sırasında bağlantı hatası oluştu." });
    };
    xhr.onabort = () => {
      xhrRef.current = null;
      resolve({ ok: false, aborted: true, message: "Yükleme iptal edildi." });
    };
    xhr.send(file);
  });
}

const accepts: Record<Exclude<ContentKind, "link">, string> = {
  video: "video/mp4,video/webm",
  document: "application/pdf",
};

function FieldMsg({ errors, name }: { errors: FieldErrors; name: string }) {
  const m = errors[name]?.[0];
  return m ? <p className="text-sm text-destructive">{m}</p> : null;
}

export function AddContentDialog(props: Props) {
  const [open, setOpen] = useState(false);
  const [kind, setKind] = useState<ContentKind>("video");
  const [title, setTitle] = useState("");
  const [required, setRequired] = useState(true);
  const [url, setUrl] = useState("");
  const [file, setFile] = useState<File | null>(null);
  const [errors, setErrors] = useState<FieldErrors>({});
  const [busy, setBusy] = useState(false);
  const [progress, setProgress] = useState<number | null>(null);
  const [duration, setDuration] = useState<number | null>(null);
  const [reading, setReading] = useState(false);
  const xhrRef = useRef<XMLHttpRequest | null>(null);
  const cancelled = useRef(false);
  const readSeq = useRef(0);

  async function pickFile(f: File | null, k: ContentKind) {
    const seq = ++readSeq.current;
    setFile(f);
    setDuration(null);
    setErrors((prev) => ({ ...prev, file: [] }));
    if (!f || k !== "video") {
      setReading(false);
      return;
    }
    setReading(true);
    const d = await readVideoDuration(f);
    if (seq !== readSeq.current) return;
    setReading(false);
    if (d === null || d > 86400) {
      setErrors((prev) => ({ ...prev, file: [DURATION_ERROR] }));
    } else {
      setDuration(Math.round(d) > 0 ? Math.round(d) : 1);
    }
  }

  function reset() {
    readSeq.current++;
    setDuration(null);
    setReading(false);
    setKind("video");
    setTitle("");
    setRequired(true);
    setUrl("");
    setFile(null);
    setErrors({});
    setProgress(null);
  }

  function changeOpen(next: boolean) {
    if (!next) {
      cancelled.current = true;
      xhrRef.current?.abort();
      setBusy(false);
      reset();
    }
    setOpen(next);
  }

  function cancelUpload() {
    cancelled.current = true;
    xhrRef.current?.abort();
  }

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (busy) return;
    cancelled.current = false;
    setErrors({});
    setBusy(true);
    try {
      let result: ActionResult;
      if (kind === "link") {
        result = await props.create({ type: "link", title, is_required: required, url });
      } else {
        if (!file) {
          setErrors({ file: ["Bir dosya seçin."] });
          return;
        }
        if (kind === "video" && duration === null) {
          setErrors({ file: [DURATION_ERROR] });
          return;
        }
        const target = await props.requestUpload({
          kind,
          filename: file.name,
          mime_type: file.type,
          size_bytes: file.size,
        });
        if (!target.ok) {
          setErrors(target.fieldErrors ?? {});
          notify(target);
          return;
        }
        setProgress(0);
        const up = await putFile(target.target, file, xhrRef, setProgress);
        if (!up.ok) {
          setProgress(null);
          if (up.aborted) toast_cancelled();
          else notify({ ok: false, message: up.message });
          return;
        }
        if (cancelled.current) return;
        result = await props.create({
          type: kind,
          title,
          is_required: required,
          upload_id: target.target.upload_id,
          ...(kind === "video" && duration !== null ? { duration_seconds: duration } : {}),
        });
      }
      if (!result.ok) {
        const fe = { ...(result.fieldErrors ?? {}) };
        if (fe.upload_id) fe.file = fe.upload_id;
        if (fe.duration_seconds) fe.file = [...(fe.file ?? []), ...fe.duration_seconds];
        setErrors(fe);
        notify(result);
        return;
      }
      notify(result);
      changeOpen(false);
    } finally {
      setBusy(false);
    }
  }

  const uploading = busy && progress !== null;

  return (
    <Dialog open={open} onOpenChange={changeOpen}>
      <DialogTrigger render={<Button />}>İçerik ekle</DialogTrigger>
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-md">
        <DialogHeader>
          <DialogTitle>İçerik ekle</DialogTitle>
          <DialogDescription>Video (MP4/WebM, en fazla 200 MB), PDF (en fazla 50 MB) ya da link ekleyin.</DialogDescription>
        </DialogHeader>
        <form className="space-y-4" onSubmit={submit}>
          <div className="space-y-1.5">
            <Label htmlFor="content-kind">Tür</Label>
            <select
              id="content-kind"
              className={selectClass}
              value={kind}
              disabled={busy}
              onChange={(e) => {
                setKind(e.target.value as ContentKind);
                void pickFile(null, "link");
                setErrors({});
              }}
            >
              {(Object.keys(contentKinds) as ContentKind[]).map((k) => (
                <option key={k} value={k}>
                  {contentKinds[k].label}
                </option>
              ))}
            </select>
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="content-title">Başlık</Label>
            <Input
              id="content-title"
              value={title}
              disabled={busy}
              required
              maxLength={150}
              aria-invalid={!!errors.title}
              onChange={(e) => setTitle(e.target.value)}
            />
            <FieldMsg errors={errors} name="title" />
          </div>

          {kind === "link" ? (
            <div className="space-y-1.5">
              <Label htmlFor="content-url">Bağlantı adresi</Label>
              <Input
                id="content-url"
                type="url"
                placeholder="https://"
                value={url}
                disabled={busy}
                required
                aria-invalid={!!errors.url}
                onChange={(e) => setUrl(e.target.value)}
              />
              <FieldMsg errors={errors} name="url" />
            </div>
          ) : (
            <div className="space-y-1.5">
              <Label htmlFor="content-file">Dosya</Label>
              <Input
                id="content-file"
                key={kind}
                type="file"
                accept={accepts[kind]}
                disabled={busy}
                aria-invalid={!!errors.file}
                onChange={(e) => void pickFile(e.target.files?.[0] ?? null, kind)}
              />
              {file && (
                <p className="text-xs text-muted-foreground">
                  {formatBytes(file.size)}
                  {kind === "video" && reading && " · Süre okunuyor..."}
                  {kind === "video" && duration !== null && ` · Süre: ${formatDuration(duration)}`}
                </p>
              )}
              <FieldMsg errors={errors} name="file" />
            </div>
          )}

          <div className="flex items-center gap-2">
            <Checkbox
              id="content-required"
              checked={required}
              disabled={busy}
              onCheckedChange={(v) => setRequired(v === true)}
            />
            <Label htmlFor="content-required">Zorunlu içerik</Label>
          </div>

          {progress !== null && (
            <Progress value={progress}>
              <ProgressLabel>Yükleniyor</ProgressLabel>
              <ProgressValue>{() => `%${progress}`}</ProgressValue>
            </Progress>
          )}

          <div className="flex justify-end gap-2 pt-2">
            {uploading && (
              <Button type="button" variant="outline" onClick={cancelUpload}>
                Yüklemeyi iptal et
              </Button>
            )}
            <Button type="submit" disabled={busy || reading}>
              {busy ? (uploading ? "Yükleniyor..." : "İşleniyor...") : "Ekle"}
            </Button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function toast_cancelled() {
  notify({ ok: false, message: "Yükleme iptal edildi." });
}
