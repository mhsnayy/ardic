"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { reportProgressAction } from "../actions";
import type { PlayerProps } from "./learn-kinds";

export function VideoPlayer({ token, content }: PlayerProps) {
  const router = useRouter();
  const ref = useRef<HTMLVideoElement>(null);
  const inflight = useRef(false);
  const localMax = useRef(content.progress.max_position_seconds);
  const lastTime = useRef(0);
  const completedRef = useRef(content.progress.completed);
  const timer = useRef<ReturnType<typeof setInterval> | null>(null);
  const [note, setNote] = useState(false);
  const retried = useRef(false);
  const resumeAt = useRef<number | null>(null);
  // Süre sunucudan gelir (admin yüklerken tarayıcıdan okunmuş değer).
  const serverDuration = content.properties.duration_seconds ?? null;

  const report = useCallback(async () => {
    const v = ref.current;
    if (!v || inflight.current) return;
    inflight.current = true;
    try {
      const r = await reportProgressAction(token, content.id, {
        position: v.currentTime,
      });
      if (!r.ok) {
        if (r.invalid) router.refresh();
        return;
      }
      localMax.current = Math.max(localMax.current, r.data.max_position_seconds);
      if (r.data.completed && !completedRef.current) {
        completedRef.current = true;
        router.refresh();
      }
    } finally {
      inflight.current = false;
    }
  }, [token, content.id, router]);

  useEffect(() => {
    const onHidden = () => {
      if (document.visibilityState === "hidden") void report();
    };
    document.addEventListener("visibilitychange", onHidden);
    return () => document.removeEventListener("visibilitychange", onHidden);
  }, [report]);

  useEffect(
    () => () => {
      if (timer.current) clearInterval(timer.current);
    },
    [],
  );

  function stopTimer() {
    if (timer.current) clearInterval(timer.current);
    timer.current = null;
  }

  return (
    <div className="space-y-2">
      <video
        ref={ref}
        controls
        preload="metadata"
        playsInline
        src={content.playback_url ?? undefined}
        className="w-full rounded-md bg-black"
        onError={(e) => {
          // Oynatma URL'i süresi dolmuş olabilir (403): bir kez yenileyip kaldığı yerden devam et.
          if (retried.current) return;
          retried.current = true;
          const v = e.currentTarget;
          resumeAt.current = v.currentTime > 0 ? v.currentTime : lastTime.current;
          router.refresh();
        }}
        onLoadedMetadata={(e) => {
          const v = e.currentTarget;
          retried.current = false;
          const p = resumeAt.current ?? content.progress.last_position_seconds;
          resumeAt.current = null;
          const total = serverDuration ?? v.duration;
          if (p > 0 && (!Number.isFinite(total) || p < total)) {
            v.currentTime = p;
            lastTime.current = p;
          }
        }}
        onTimeUpdate={(e) => {
          const v = e.currentTarget;
          const delta = v.currentTime - lastTime.current;
          // Doğal oynatmada (küçük adımlarla) yerel maksimumu ilerlet
          if (!v.seeking && delta >= 0 && delta < 2) {
            localMax.current = Math.max(localMax.current, v.currentTime);
          }
          lastTime.current = v.currentTime;
        }}
        onPlay={() => {
          stopTimer();
          timer.current = setInterval(() => void report(), 10000);
        }}
        onPause={() => {
          stopTimer();
          void report();
        }}
        onEnded={() => {
          stopTimer();
          void report();
        }}
        onSeeking={(e) => {
          const v = e.currentTarget;
          const limit = serverDuration !== null ? Math.min(localMax.current, serverDuration) : localMax.current;
          if (v.currentTime > limit + 2) {
            v.currentTime = limit;
            setNote(true);
            setTimeout(() => setNote(false), 4000);
          }
        }}
      />
      {note && <p className="text-sm text-amber-700 dark:text-amber-300">İleri sarma ilerlemeye sayılmaz.</p>}
    </div>
  );
}
