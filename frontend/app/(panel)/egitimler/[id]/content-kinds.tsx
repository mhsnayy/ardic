import { FileText, Link2, Video, type LucideIcon } from "lucide-react";
import type { Content, ContentKind } from "@/lib/api/types";
import { formatBytes, formatDuration } from "@/lib/format";

type Details = (props: { content: Content }) => React.ReactNode;

const VideoDetails: Details = ({ content }) => {
  const d = formatDuration(content.properties.duration_seconds);
  return (
    <>
      {d ? `Süre ${d} · ` : ""}
      {formatBytes(content.properties.size_bytes)}
    </>
  );
};

const DocumentDetails: Details = ({ content }) => <>{formatBytes(content.properties.size_bytes)}</>;

const LinkDetails: Details = ({ content }) => <span className="break-all">{content.properties.url ?? "-"}</span>;

/** 02 §5: kind -> bileşen haritası (if/else zinciri yok). */
export const contentKinds = {
  video: { label: "Video", icon: Video, Details: VideoDetails },
  document: { label: "PDF", icon: FileText, Details: DocumentDetails },
  link: { label: "Link", icon: Link2, Details: LinkDetails },
} satisfies Record<ContentKind, { label: string; icon: LucideIcon; Details: Details }>;
