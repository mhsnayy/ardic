import Link from "next/link";
import { Button } from "@/components/ui/button";

export function NotFoundView() {
  return (
    <div className="flex flex-1 flex-col items-center justify-center gap-4 p-8 text-center">
      <h2 className="text-xl font-semibold">Sayfa bulunamadı</h2>
      <p className="text-sm text-muted-foreground">Aradığınız sayfa ya da kayıt mevcut değil.</p>
      <Button render={<Link href="/" />}>Ana sayfaya dön</Button>
    </div>
  );
}
