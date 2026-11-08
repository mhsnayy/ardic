import Link from "next/link";
import { Button } from "@/components/ui/button";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { listEpDepartments, listPersonnelProgress } from "@/lib/api/education";
import { FilterBar, PageHeader, PersonnelSourceAlert, ProgressCell } from "@/components/ui-bits";

export const dynamic = "force-dynamic";

export default async function PersonnelProgressPage({ searchParams }: PageProps<"/egitimler/personel">) {
  const sp = await searchParams;
  const one = (v: string | string[] | undefined) => (Array.isArray(v) ? v[0] : v);
  const search = one(sp.search);
  const departmentId = one(sp.department_id);

  const [people, deps] = await Promise.all([
    listPersonnelProgress({ search, department_id: departmentId }),
    listEpDepartments(),
  ]);
  const sources = [people.meta.personnel_source, deps.meta.personnel_source];
  const source = sources.includes("unavailable") ? "unavailable" : sources.includes("stale") ? "stale" : "live";

  return (
    <>
      <PageHeader title="Personel İlerlemesi" description="Kişi bazında eğitim tamamlanma durumu" />
      <PersonnelSourceAlert source={source} />
      <FilterBar
        search={search}
        departmentId={departmentId}
        departments={deps.data}
        searchPlaceholder="Ad veya e-posta ara..."
      />

      <div className="rounded-lg border bg-card">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Ad Soyad</TableHead>
              <TableHead>E-posta</TableHead>
              <TableHead>Departman</TableHead>
              <TableHead>İlerleme</TableHead>
              <TableHead className="text-right">Detay</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {people.data.length === 0 && (
              <TableRow>
                <TableCell colSpan={5} className="py-8 text-center text-muted-foreground">
                  {source === "unavailable" ? "Personel listesi şu an gösterilemiyor." : "Personel bulunamadı."}
                </TableCell>
              </TableRow>
            )}
            {people.data.map((p) => (
              <TableRow key={p.id}>
                <TableCell className="font-medium">{p.full_name}</TableCell>
                <TableCell>{p.email}</TableCell>
                <TableCell>{p.department.name}</TableCell>
                <TableCell>
                  <ProgressCell
                    completed={p.progress.completed}
                    total={p.progress.total}
                    rate={p.progress.completion_rate}
                  />
                </TableCell>
                <TableCell className="text-right">
                  <Button variant="ghost" size="sm" render={<Link href={`/personel/${p.id}`} />}>
                    Eğitimleri
                  </Button>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
    </>
  );
}
