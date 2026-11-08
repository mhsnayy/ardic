import { FileText, Link2, Video, type LucideIcon } from "lucide-react";
import type { ContentKind, LearnContent } from "@/lib/api/types";
import { DocumentPlayer, LinkPlayer } from "./open-content";
import { VideoPlayer } from "./video-player";

export type PlayerProps = { token: string; content: LearnContent };

/** 02 §5: kind -> bileşen haritası. */
export const learnKinds = {
  video: { label: "Video", icon: Video, Player: VideoPlayer },
  document: { label: "PDF", icon: FileText, Player: DocumentPlayer },
  link: { label: "Link", icon: Link2, Player: LinkPlayer },
} satisfies Record<ContentKind, { label: string; icon: LucideIcon; Player: (p: PlayerProps) => React.ReactNode }>;
