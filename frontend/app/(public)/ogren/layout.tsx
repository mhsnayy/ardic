import type { Metadata } from "next";

// Token URL'de olduğu için Referer ile sızmasın, arama motorları indekslemesin.
export const metadata: Metadata = {
  title: "Eğitim",
  referrer: "no-referrer",
  robots: { index: false, follow: false },
};

export default function PublicLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="min-h-screen bg-muted/30">
      <header className="border-b bg-card p-4 text-center font-semibold">Eğitim Portalı</header>
      <main className="mx-auto w-full max-w-3xl p-4 md:p-8">{children}</main>
    </div>
  );
}
