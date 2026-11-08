import type { AssignmentStatus } from "./api/types";

export const STATUS_LABELS: Record<AssignmentStatus, string> = {
  assigned: "Atandı",
  in_progress: "Devam Ediyor",
  completed: "Tamamlandı",
};

export const STATUS_CLASSES: Record<AssignmentStatus, string> = {
  assigned: "bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200",
  in_progress: "bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200",
  completed: "bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200",
};

export function formatDate(value: string | null | undefined): string {
  if (!value) return "-";
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return "-";
  return d.toLocaleString("tr-TR", {
    dateStyle: "short",
    timeStyle: "short",
    timeZone: "Europe/Istanbul",
  });
}

export function formatPercent(rate: number): string {
  return `%${Math.round(rate)}`;
}

export function formatBytes(bytes: number | undefined | null): string {
  if (bytes === undefined || bytes === null) return "-";
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export function formatDuration(seconds: number | undefined | null): string | null {
  if (seconds === undefined || seconds === null) return null;
  const s = Math.round(seconds);
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const r = String(s % 60).padStart(2, "0");
  return h > 0 ? `${h}:${String(m).padStart(2, "0")}:${r}` : `${m}:${r}`;
}

export function formatDay(value: string | null | undefined): string {
  if (!value) return "-";
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return "-";
  return d.toLocaleDateString("tr-TR", { timeZone: "Europe/Istanbul" });
}
