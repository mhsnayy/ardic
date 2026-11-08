"use client";

import { Button } from "@/components/ui/button";

export function ErrorView({ reset }: { reset: () => void }) {
  return (
    <div className="flex flex-1 flex-col items-center justify-center gap-4 p-8 text-center">
      <h2 className="text-xl font-semibold">Bir şeyler ters gitti</h2>
      <p className="max-w-md text-sm text-muted-foreground">
        İstek tamamlanamadı. Sunucuya ulaşılamıyor olabilir ya da bu işlem için yetkiniz bulunmuyor olabilir.
      </p>
      <Button onClick={() => reset()}>Tekrar dene</Button>
    </div>
  );
}
