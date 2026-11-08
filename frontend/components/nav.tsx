"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useState } from "react";
import { Menu } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Sheet, SheetContent, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { cn } from "@/lib/utils";

const items = [
  { href: "/", label: "Özet" },
  { href: "/egitimler", label: "Eğitimler" },
  { href: "/egitimler/personel", label: "Personel İlerlemesi" },
  { href: "/personel", label: "Personel" },
  { href: "/departmanlar", label: "Departmanlar" },
];

function isActive(pathname: string, href: string) {
  if (href === "/") return pathname === "/";
  if (href === "/egitimler") {
    return pathname === "/egitimler" || (pathname.startsWith("/egitimler/") && !pathname.startsWith("/egitimler/personel"));
  }
  return pathname === href || pathname.startsWith(href + "/");
}

function NavLinks({ onNavigate }: { onNavigate?: () => void }) {
  const pathname = usePathname();
  return (
    <nav className="flex flex-col gap-1">
      {items.map((i) => (
        <Link
          key={i.href}
          href={i.href}
          onClick={onNavigate}
          className={cn(
            "rounded-lg px-3 py-2 text-sm font-medium transition-colors hover:bg-muted",
            isActive(pathname, i.href) ? "bg-muted text-foreground" : "text-muted-foreground",
          )}
        >
          {i.label}
        </Link>
      ))}
    </nav>
  );
}

export function DesktopNav() {
  return <NavLinks />;
}

export function MobileNav({ logout }: { logout: React.ReactNode }) {
  const [open, setOpen] = useState(false);
  return (
    <>
      <Button variant="outline" size="icon" aria-label="Menüyü aç" onClick={() => setOpen(true)}>
        <Menu />
      </Button>
      <Sheet open={open} onOpenChange={setOpen}>
        <SheetContent side="left" className="w-64">
          <SheetHeader>
            <SheetTitle>Eğitim Yönetim Paneli</SheetTitle>
          </SheetHeader>
          <div className="flex flex-1 flex-col justify-between gap-4 px-4 pb-4">
            <NavLinks onNavigate={() => setOpen(false)} />
            {logout}
          </div>
        </SheetContent>
      </Sheet>
    </>
  );
}
