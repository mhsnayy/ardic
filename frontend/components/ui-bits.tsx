import Link from "next/link";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import type { AssignmentStatus, PersonnelSource } from "@/lib/api/types";
import { formatPercent, STATUS_CLASSES, STATUS_LABELS } from "@/lib/format";
import { cn } from "@/lib/utils";

export function PageHeader({
  title,
  description,
  children,
}: {
  title: string;
  description?: string;
  children?: React.ReactNode;
}) {
  return (
    <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
        {description && <p className="text-sm text-muted-foreground">{description}</p>}
      </div>
      {children && <div className="flex flex-wrap gap-2">{children}</div>}
    </div>
  );
}

export function StatusBadge({ status, label }: { status: AssignmentStatus; label?: string }) {
  return (
    <Badge variant="secondary" className={cn("border-transparent", STATUS_CLASSES[status])}>
      {label ?? STATUS_LABELS[status]}
    </Badge>
  );
}

export function ProgressCell({
  completed,
  total,
  rate,
}: {
  completed: number;
  total: number;
  rate: number;
}) {
  return (
    <div className="min-w-36 space-y-1">
      <div className="flex justify-between text-xs text-muted-foreground">
        <span>
          {completed}/{total} tamamlandı
        </span>
        <span className="font-medium text-foreground">{formatPercent(rate)}</span>
      </div>
      <Progress value={Math.min(100, Math.max(0, rate))} />
    </div>
  );
}

/** FE-3: Personel Sistemi durum uyarısı. */
export function PersonnelSourceAlert({ source }: { source: PersonnelSource | undefined }) {
  if (!source || source === "live") return null;
  const unavailable = source === "unavailable";
  return (
    <Alert variant={unavailable ? "destructive" : "default"} className="mb-4">
      <AlertTitle>Personel Sistemi&apos;ne ulaşılamıyor</AlertTitle>
      <AlertDescription>
        {unavailable
          ? "Personel Sistemi'ne ulaşılamıyor; personel listesi şu an gösterilemiyor, atama yapılamaz."
          : "Personel Sistemi'ne ulaşılamıyor, son bilinen veriler gösteriliyor."}
      </AlertDescription>
    </Alert>
  );
}

export function Pagination({
  basePath,
  params,
  current,
  last,
}: {
  basePath: string;
  params: Record<string, string | undefined>;
  current: number;
  last: number;
}) {
  if (last <= 1) return null;
  const href = (page: number) => {
    const q = new URLSearchParams();
    for (const [k, v] of Object.entries(params)) if (v && k !== "page") q.set(k, v);
    if (page > 1) q.set("page", String(page));
    const s = q.toString();
    return s ? `${basePath}?${s}` : basePath;
  };
  return (
    <nav className="mt-4 flex items-center justify-between gap-2" aria-label="Sayfalama">
      <Button variant="outline" size="sm" disabled={current <= 1} render={current > 1 ? <Link href={href(current - 1)} /> : undefined}>
        Önceki
      </Button>
      <span className="text-sm text-muted-foreground">
        Sayfa {current} / {last}
      </span>
      <Button variant="outline" size="sm" disabled={current >= last} render={current < last ? <Link href={href(current + 1)} /> : undefined}>
        Sonraki
      </Button>
    </nav>
  );
}

export function EmptyRow({ colSpan, text }: { colSpan: number; text: string }) {
  return (
    <tr>
      <td colSpan={colSpan} className="p-6 text-center text-sm text-muted-foreground">
        {text}
      </td>
    </tr>
  );
}

/** GET formu: JS gerektirmeyen arama + filtre çubuğu. */
export function FilterBar({
  search,
  departmentId,
  departments,
  searchPlaceholder = "Ara...",
}: {
  search?: string;
  departmentId?: string;
  departments?: { id: number; name: string }[];
  searchPlaceholder?: string;
}) {
  return (
    <form method="get" className="mb-4 flex flex-col gap-2 sm:flex-row">
      <input
        type="search"
        name="search"
        defaultValue={search ?? ""}
        placeholder={searchPlaceholder}
        className="h-8 w-full rounded-lg border border-input bg-transparent px-2.5 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 sm:max-w-xs dark:bg-input/30"
      />
      {departments && (
        <select
          name="department_id"
          defaultValue={departmentId ?? ""}
          className="h-8 rounded-lg border border-input bg-transparent px-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 sm:w-56 dark:bg-input/30"
        >
          <option value="">Tüm departmanlar</option>
          {departments.map((d) => (
            <option key={d.id} value={d.id}>
              {d.name}
            </option>
          ))}
        </select>
      )}
      <div className="flex gap-2">
        <Button type="submit" size="sm" className="h-8">
          Ara
        </Button>
        {(search || departmentId) && (
          <Button variant="ghost" size="sm" className="h-8" render={<Link href="?" />}>
            Temizle
          </Button>
        )}
      </div>
    </form>
  );
}
