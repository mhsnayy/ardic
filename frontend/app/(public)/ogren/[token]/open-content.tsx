"use client";

import { useRouter } from "next/navigation";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { reportProgressAction } from "../actions";
import type { PlayerProps } from "./learn-kinds";

function OpenLink({ token, content, href, label }: PlayerProps & { href: string | null | undefined; label: string }) {
  const router = useRouter();
  if (!href) return <p className="text-sm text-muted-foreground">Bu içerik şu anda açılamıyor.</p>;
  return (
    <a
      href={href}
      target="_blank"
      rel="noopener noreferrer"
      className={cn(buttonVariants({ variant: "outline" }), "w-full sm:w-auto")}
      onClick={async () => {
        const r = await reportProgressAction(token, content.id, {});
        if (r.ok ? !content.progress.completed && r.data.completed : r.invalid) router.refresh();
      }}
    >
      {label}
    </a>
  );
}

export function DocumentPlayer(props: PlayerProps) {
  return <OpenLink {...props} href={props.content.playback_url} label="PDF'i aç" />;
}

export function LinkPlayer(props: PlayerProps) {
  return <OpenLink {...props} href={props.content.properties.url} label="Bağlantıyı aç" />;
}
