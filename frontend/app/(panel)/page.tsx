import Link from "next/link";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { listEducations, listPersonnelProgress } from "@/lib/api/education";
import type { Education } from "@/lib/api/types";
import { formatPercent } from "@/lib/format";
import { PageHeader, PersonnelSourceAlert, ProgressCell } from "@/components/ui-bits";

export const dynamic = "force-dynamic";

export default async function DashboardPage() {
  const first = await listEducations({ page: 1 });
  const all: Education[] = [...first.data];
  // Genel toplam için kalan sayfalar (en fazla 20 sayfa)
  const last = Math.min(first.meta.last_page, 20);
  if (last > 1) {
    const rest = await Promise.all(
      Array.from({ length: last - 1 }, (_, i) => listEducations({ page: i + 2 })),
    );
    for (const r of rest) all.push(...r.data);
  }
  const people = await listPersonnelProgress({});

  const total = all.reduce((s, e) => s + e.stats.total, 0);
  const completed = all.reduce((s, e) => s + e.stats.completed, 0);
  const inProgress = all.reduce((s, e) => s + e.stats.in_progress, 0);
  const rate = total > 0 ? (completed / total) * 100 : 0;

  const cards = [
    { label: "Eğitim sayısı", value: first.meta.total },
    { label: "Toplam atama", value: total },
    { label: "Devam eden", value: inProgress },
    { label: "Tamamlanan", value: `${completed} (${formatPercent(rate)})` },
    {
      label: "Personel sayısı",
      value: people.meta.personnel_source === "unavailable" ? "-" : people.data.length,
    },
  ];

  return (
    <>
      <PageHeader title="Özet" description="Eğitim ve atama durumuna genel bakış">
        <Button render={<Link href="/egitimler" />}>Eğitimlere git</Button>
      </PageHeader>

      <PersonnelSourceAlert source={people.meta.personnel_source} />

      <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        {cards.map((c) => (
          <Card key={c.label} size="sm">
            <CardHeader>
              <CardTitle className="text-sm font-medium text-muted-foreground">{c.label}</CardTitle>
            </CardHeader>
            <CardContent className="text-2xl font-semibold">{c.value}</CardContent>
          </Card>
        ))}
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Genel tamamlanma oranı</CardTitle>
        </CardHeader>
        <CardContent>
          <ProgressCell completed={completed} total={total} rate={rate} />
        </CardContent>
      </Card>
    </>
  );
}
