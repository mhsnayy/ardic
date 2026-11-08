import Link from "next/link";
import { Button } from "@/components/ui/button";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { listEducations, listEducationTypes } from "@/lib/api/education";
import { ConfirmButton, FormDialog } from "@/components/forms";
import { EducationFields } from "@/components/education-fields";
import { FilterBar, PageHeader, Pagination, ProgressCell } from "@/components/ui-bits";
import { createEducationAction, deleteEducationAction, updateEducationAction } from "./actions";

export const dynamic = "force-dynamic";

export default async function EducationsPage({ searchParams }: PageProps<"/egitimler">) {
  const sp = await searchParams;
  const one = (v: string | string[] | undefined) => (Array.isArray(v) ? v[0] : v);
  const search = one(sp.search);
  const page = one(sp.page);

  const [list, types] = await Promise.all([listEducations({ search, page }), listEducationTypes()]);

  return (
    <>
      <PageHeader title="Eğitimler" description={`Toplam ${list.meta.total} eğitim`}>
        <FormDialog action={createEducationAction} title="Yeni eğitim" triggerLabel="Yeni eğitim" submitLabel="Oluştur">
          <EducationFields types={types.data} />
        </FormDialog>
      </PageHeader>

      <FilterBar search={search} searchPlaceholder="Eğitim ara..." />

      <div className="rounded-lg border bg-card">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Eğitim</TableHead>
              <TableHead>Tür</TableHead>
              <TableHead>İlerleme</TableHead>
              <TableHead className="text-right">İşlemler</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {list.data.length === 0 && (
              <TableRow>
                <TableCell colSpan={4} className="py-8 text-center text-muted-foreground">
                  Eğitim bulunamadı.
                </TableCell>
              </TableRow>
            )}
            {list.data.map((e) => (
              <TableRow key={e.id}>
                <TableCell className="font-medium">
                  <Link href={`/egitimler/${e.id}`} className="hover:underline">
                    {e.name}
                  </Link>
                </TableCell>
                <TableCell>{e.type_label}</TableCell>
                <TableCell>
                  <ProgressCell completed={e.stats.completed} total={e.stats.total} rate={e.stats.completion_rate} />
                </TableCell>
                <TableCell>
                  <div className="flex justify-end gap-2">
                    <Button variant="ghost" size="sm" render={<Link href={`/egitimler/${e.id}`} />}>
                      Detay
                    </Button>
                    <FormDialog
                      action={updateEducationAction.bind(null, e.id)}
                      title="Eğitimi düzenle"
                      triggerLabel="Düzenle"
                      triggerVariant="outline"
                    >
                      <EducationFields types={types.data} e={e} />
                    </FormDialog>
                    <ConfirmButton
                      action={deleteEducationAction.bind(null, e.id)}
                      label="Sil"
                      title="Eğitim silinsin mi?"
                      description={`"${e.name}" ve tüm atamaları silinecek.`}
                    />
                  </div>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      <Pagination
        basePath="/egitimler"
        params={{ search }}
        current={list.meta.current_page}
        last={list.meta.last_page}
      />
    </>
  );
}
