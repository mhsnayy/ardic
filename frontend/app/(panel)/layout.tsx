import { Button } from "@/components/ui/button";
import { DesktopNav, MobileNav } from "@/components/nav";
import { logoutAction } from "./actions";

function LogoutButton() {
  return (
    <form action={logoutAction}>
      <Button type="submit" variant="outline" className="w-full">
        Çıkış
      </Button>
    </form>
  );
}

export default function PanelLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex min-h-screen">
      <aside className="hidden w-60 shrink-0 flex-col justify-between border-r bg-card p-4 md:flex">
        <div className="space-y-6">
          <div className="px-3 text-base font-semibold">Eğitim Yönetim Paneli</div>
          <DesktopNav />
        </div>
        <LogoutButton />
      </aside>
      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex items-center gap-3 border-b bg-card p-3 md:hidden">
          <MobileNav logout={<LogoutButton />} />
          <span className="font-semibold">Eğitim Yönetim Paneli</span>
        </header>
        <main className="flex min-w-0 flex-1 flex-col p-4 md:p-8">{children}</main>
      </div>
    </div>
  );
}
