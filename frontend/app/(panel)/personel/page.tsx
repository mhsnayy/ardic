import Link from "next/link";
import { Button } from "@/components/ui/button";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { listAdminDepartments, listPersonnel } from "@/lib/api/personnel";
import type { Personnel } from "@/lib/api/types";
import { ConfirmButton, Field, FormDialog, SelectField } from "@/components/forms";
import { FilterBar, PageHeader, Pagination } from "@/components/ui-bits";
import { createPersonnelAction, deletePersonnelAction, updatePersonnelAction } from "./actions";

export const dynamic = "force-dynamic";

function PersonnelFields({
  departments,
  p,
}: {
  departments: { id: number; name: string }[];
  p?: Personnel;
}) {
  return (
    <>
      <Field name="first_name" label="Ad" defaultValue={p?.first_name} />
      <Field name="last_name" label="Soyad" defaultValue={p?.last_name} />
      <Field name="email" label="E-posta" type="email" defaultValue={p?.email} />
      <SelectField
        name="department_id"
        label="Departman"
        placeholder="Departman seçin"
        options={departments.map((d) => ({ value: d.id, label: d.name }))}
        defaultValue={p?.department.id}
      />
    </>
  );
}

export default async function PersonnelPage({ searchParams }: PageProps<"/personel">) {
  const sp = await searchParams;
  const one = (v: string | string[] | undefined) => (Array.isArray(v) ? v[0] : v);
  const search = one(sp.search);
  const departmentId = one(sp.department_id);
  const page = one(sp.page);

  const [list, deps] = await Promise.all([
    listPersonnel({ search, department_id: departmentId, page }),
    listAdminDepartments(),
  ]);

  return (
    <>
      <PageHeader title="Personel" description={`Toplam ${list.meta.total} kayıt`}>
        <FormDialog
          action={createPersonnelAction}
          title="Yeni personel"
          triggerLabel="Yeni personel"
          submitLabel="Ekle"
        >
          <PersonnelFields departments={deps.data} />
        </FormDialog>
      </PageHeader>

      <FilterBar
        search={search}
        departmentId={departmentId}
        departments={deps.data}
        searchPlaceholder="Ad, soyad veya e-posta ara..."
      />

      <div className="rounded-lg border bg-card">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Ad Soyad</TableHead>
              <TableHead>E-posta</TableHead>
              <TableHead>Departman</TableHead>
              <TableHead className="text-right">İşlemler</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {list.data.length === 0 && (
              <TableRow>
                <TableCell colSpan={4} className="py-8 text-center text-muted-foreground">
                  Kayıt bulunamadı.
                </TableCell>
              </TableRow>
            )}
            {list.data.map((p) => (
              <TableRow key={p.id}>
                <TableCell className="font-medium">{p.full_name}</TableCell>
                <TableCell>{p.email}</TableCell>
                <TableCell>{p.department.name}</TableCell>
                <TableCell>
                  <div className="flex justify-end gap-2">
                    <Button variant="ghost" size="sm" render={<Link href={`/personel/${p.id}`} />}>
                      Eğitimleri
                    </Button>
                    <FormDialog
                      action={updatePersonnelAction.bind(null, p.id)}
                      title="Personeli düzenle"
                      triggerLabel="Düzenle"
                      triggerVariant="outline"
                    >
                      <PersonnelFields departments={deps.data} p={p} />
                    </FormDialog>
                    <ConfirmButton
                      action={deletePersonnelAction.bind(null, p.id)}
                      label="Sil"
                      title="Personel silinsin mi?"
                      description={`${p.full_name} kalıcı olarak silinecek. Atama geçmişi korunur.`}
                    />
                  </div>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      <Pagination
        basePath="/personel"
        params={{ search, department_id: departmentId }}
        current={list.meta.current_page}
        last={list.meta.last_page}
      />
    </>
  );
}
