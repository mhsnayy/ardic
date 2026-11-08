import Link from "next/link";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import {
  getEducation,
  listAssignments,
  listEducationTypes,
  listEpDepartments,
  listPersonnelProgress,
} from "@/lib/api/education";
import { formatDate, formatPercent } from "@/lib/format";
import { ConfirmButton, FormDialog } from "@/components/forms";
import { EducationFields } from "@/components/education-fields";
import { PageHeader, PersonnelSourceAlert, ProgressCell, StatusBadge } from "@/components/ui-bits";
import {
  assignDepartmentAction,
  assignPersonnelAction,
  deleteAssignmentAction,
  deleteEducationAndLeaveAction,
  updateEducationAction,
  updateStatusAction,
} from "../actions";
import { AssignDialog } from "./assign-dialog";
import { StatusSelect } from "./status-select";

export const dynamic = "force-dynamic";

export default async function EducationDetailPage({ params }: PageProps<"/egitimler/[id]">) {
  const { id } = await params;

  const [{ data: edu }, assignments, types, people, deps] = await Promise.all([
    getEducation(id),
    listAssignments(id),
    listEducationTypes(),
    listPersonnelProgress({}),
    listEpDepartments(),
  ]);

  // Personel Sistemi durumu: en kötü durumu göster
  const sources = [people.meta.personnel_source, deps.meta.personnel_source];
  const source = sources.includes("unavailable") ? "unavailable" : sources.includes("stale") ? "stale" : "live";
  const assignedIds = new Set(assignments.data.map((a) => a.personnel_id));

  return (
    <>
      <PageHeader title={edu.name} description={`${edu.type_label} eğitimi`}>
        <Button variant="outline" render={<Link href="/egitimler" />}>
          Eğitimler
        </Button>
        <FormDialog
          action={updateEducationAction.bind(null, edu.id)}
          title="Eğitimi düzenle"
          triggerLabel="Düzenle"
          triggerVariant="outline"
        >
          <EducationFields types={types.data} e={edu} />
        </FormDialog>
        <ConfirmButton
          action={deleteEducationAndLeaveAction.bind(null, edu.id)}
          label="Sil"
          title="Eğitim silinsin mi?"
          description={`"${edu.name}" ve tüm atamaları silinecek.`}
        />
      </PageHeader>

      <PersonnelSourceAlert source={source} />

      <div className="mb-6 grid gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>Açıklama</CardTitle>
          </CardHeader>
          <CardContent className="text-sm whitespace-pre-wrap">
            {edu.description || <span className="text-muted-foreground">Açıklama girilmemiş.</span>}
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>İlerleme</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3 text-sm">
            <ProgressCell completed={edu.stats.completed} total={edu.stats.total} rate={edu.stats.completion_rate} />
            <dl className="grid grid-cols-2 gap-x-4 gap-y-1">
              <dt className="text-muted-foreground">Toplam atama</dt>
              <dd className="text-right font-medium">{edu.stats.total}</dd>
              <dt className="text-muted-foreground">Atandı</dt>
              <dd className="text-right font-medium">{edu.stats.assigned}</dd>
              <dt className="text-muted-foreground">Devam ediyor</dt>
              <dd className="text-right font-medium">{edu.stats.in_progress}</dd>
              <dt className="text-muted-foreground">Tamamlandı</dt>
              <dd className="text-right font-medium">
                {edu.stats.completed} ({formatPercent(edu.stats.completion_rate)})
              </dd>
            </dl>
          </CardContent>
        </Card>
      </div>

      <div className="mb-3 flex items-center justify-between gap-2">
        <h2 className="text-lg font-semibold">Atamalar</h2>
        <AssignDialog
          disabled={source === "unavailable"}
          people={people.data.map((p) => ({
            id: p.id,
            name: p.full_name,
            email: p.email,
            departmentName: p.department.name,
            assigned: assignedIds.has(p.id),
          }))}
          departments={deps.data}
          assignPeople={assignPersonnelAction.bind(null, edu.id)}
          assignDepartment={assignDepartmentAction.bind(null, edu.id)}
        />
      </div>

      <div className="rounded-lg border bg-card">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Ad</TableHead>
              <TableHead>E-posta</TableHead>
              <TableHead>Departman</TableHead>
              <TableHead>Durum</TableHead>
              <TableHead>Atanma</TableHead>
              <TableHead>Tamamlanma</TableHead>
              <TableHead className="text-right">İşlemler</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {assignments.data.length === 0 && (
              <TableRow>
                <TableCell colSpan={7} className="py-8 text-center text-muted-foreground">
                  Bu eğitime henüz kimse atanmadı.
                </TableCell>
              </TableRow>
            )}
            {assignments.data.map((a) => (
              <TableRow key={a.id}>
                <TableCell className="font-medium">
                  <Link href={`/personel/${a.personnel_id}`} className="hover:underline">
                    {a.personnel_name}
                  </Link>
                </TableCell>
                <TableCell>{a.personnel_email}</TableCell>
                <TableCell>{a.department_name ?? "-"}</TableCell>
                <TableCell>
                  <div className="flex flex-col gap-1.5">
                    <StatusBadge status={a.status} label={a.status_label} />
                    <StatusSelect value={a.status} action={updateStatusAction.bind(null, a.id)} />
                  </div>
                </TableCell>
                <TableCell>{formatDate(a.assigned_at)}</TableCell>
                <TableCell>{formatDate(a.completed_at)}</TableCell>
                <TableCell>
                  <div className="flex justify-end">
                    <ConfirmButton
                      action={deleteAssignmentAction.bind(null, a.id)}
                      label="Kaldır"
                      confirmLabel="Kaldır"
                      title="Atama kaldırılsın mı?"
                      description={`${a.personnel_name} kişisinin bu eğitim ataması kaldırılacak.`}
                    />
                  </div>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
    </>
  );
}
