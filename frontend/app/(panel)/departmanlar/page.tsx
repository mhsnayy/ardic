import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { listAdminDepartments } from "@/lib/api/personnel";
import { ConfirmButton, Field, FormDialog } from "@/components/forms";
import { PageHeader } from "@/components/ui-bits";
import { createDepartmentAction, deleteDepartmentAction, updateDepartmentAction } from "./actions";

export const dynamic = "force-dynamic";

export default async function DepartmentsPage() {
  const { data } = await listAdminDepartments();

  return (
    <>
      <PageHeader title="Departmanlar" description={`Toplam ${data.length} departman`}>
        <FormDialog action={createDepartmentAction} title="Yeni departman" triggerLabel="Yeni departman" submitLabel="Ekle">
          <Field name="name" label="Departman adı" />
        </FormDialog>
      </PageHeader>

      <div className="rounded-lg border bg-card">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Departman</TableHead>
              <TableHead>Personel sayısı</TableHead>
              <TableHead className="text-right">İşlemler</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {data.length === 0 && (
              <TableRow>
                <TableCell colSpan={3} className="py-8 text-center text-muted-foreground">
                  Henüz departman yok.
                </TableCell>
              </TableRow>
            )}
            {data.map((d) => (
              <TableRow key={d.id}>
                <TableCell className="font-medium">{d.name}</TableCell>
                <TableCell>{d.personnel_count}</TableCell>
                <TableCell>
                  <div className="flex justify-end gap-2">
                    <FormDialog
                      action={updateDepartmentAction.bind(null, d.id)}
                      title="Departmanı düzenle"
                      triggerLabel="Düzenle"
                      triggerVariant="outline"
                    >
                      <Field name="name" label="Departman adı" defaultValue={d.name} />
                    </FormDialog>
                    <ConfirmButton
                      action={deleteDepartmentAction.bind(null, d.id)}
                      label="Sil"
                      title="Departman silinsin mi?"
                      description={`"${d.name}" departmanı silinecek. İçinde personel varsa silinemez.`}
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
