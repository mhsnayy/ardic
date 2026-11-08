import { notFound } from "next/navigation";
import { parseId } from "@/lib/validate";
import Link from "next/link";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { listPersonnelAssignments } from "@/lib/api/education";
import { formatDate, formatPercent } from "@/lib/format";
import { PageHeader, PersonnelSourceAlert, ProgressCell, StatusBadge } from "@/components/ui-bits";

export const dynamic = "force-dynamic";

export default async function PersonnelDetailPage({ params }: PageProps<"/personel/[id]">) {
  const { id } = await params;
  if (parseId(id) === null) notFound();
  // Eğitim Portalı'ndan okunur (FE-3: Personel Sistemi kapalıyken de açılır)
  const res = await listPersonnelAssignments(id);
  const { personnel, progress, personnel_source } = res.meta;
  const first = res.data[0];
  const name = personnel?.full_name ?? first?.personnel_name ?? `Personel #${id}`;
  const email = personnel?.email ?? first?.personnel_email;
  const dept = personnel?.department.name ?? first?.department_name;

  return (
    <>
      <PageHeader title={name} description={[email, dept].filter(Boolean).join(" · ")}>
        <Button variant="outline" render={<Link href="/personel" />}>
          Personel listesi
        </Button>
      </PageHeader>

      <PersonnelSourceAlert source={personnel_source} />

      <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Stat label="Toplam eğitim" value={progress.total} />
        <Stat label="Atandı" value={progress.assigned} />
        <Stat label="Devam ediyor" value={progress.in_progress} />
        <Stat label="Tamamlandı" value={`${progress.completed} (${formatPercent(progress.completion_rate)})`} />
      </div>

      <Card className="mb-6">
        <CardHeader>
          <CardTitle>Genel ilerleme</CardTitle>
        </CardHeader>
        <CardContent>
          <ProgressCell completed={progress.completed} total={progress.total} rate={progress.completion_rate} />
        </CardContent>
      </Card>

      <div className="rounded-lg border bg-card">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Eğitim</TableHead>
              <TableHead>Tür</TableHead>
              <TableHead>Durum</TableHead>
              <TableHead>Atanma</TableHead>
              <TableHead>Tamamlanma</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {res.data.length === 0 && (
              <TableRow>
                <TableCell colSpan={5} className="py-8 text-center text-muted-foreground">
                  Bu personele atanmış eğitim yok.
                </TableCell>
              </TableRow>
            )}
            {res.data.map((a) => (
              <TableRow key={a.id}>
                <TableCell className="font-medium">
                  <Link href={`/egitimler/${a.education.id}`} className="hover:underline">
                    {a.education.name}
                  </Link>
                </TableCell>
                <TableCell>{a.education.type_label}</TableCell>
                <TableCell>
                  <StatusBadge status={a.status} label={a.status_label} />
                </TableCell>
                <TableCell>{formatDate(a.assigned_at)}</TableCell>
                <TableCell>{formatDate(a.completed_at)}</TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
    </>
  );
}

function Stat({ label, value }: { label: string; value: string | number }) {
  return (
    <Card size="sm">
      <CardHeader>
        <CardTitle className="text-sm font-medium text-muted-foreground">{label}</CardTitle>
      </CardHeader>
      <CardContent className="text-2xl font-semibold">{value}</CardContent>
    </Card>
  );
}
