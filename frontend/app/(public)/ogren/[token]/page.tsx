import { CheckCircle2 } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import { getLearning } from "@/lib/api/learn";
import { learnKinds } from "./learn-kinds";

export const dynamic = "force-dynamic";

function Message({ title, text }: { title: string; text: string }) {
  return (
    <div className="mt-16 space-y-3 text-center">
      <h1 className="text-xl font-semibold">{title}</h1>
      <p className="text-sm text-muted-foreground">{text}</p>
    </div>
  );
}

export default async function LearnPage({ params }: PageProps<"/ogren/[token]">) {
  const { token } = await params;
  const r = await getLearning(token);

  if (r.status === "invalid") {
    return (
      <Message
        title="Link geçersiz"
        text="Bu öğrenme linki geçersiz ya da süresi dolmuş. Lütfen yöneticinizle iletişime geçin."
      />
    );
  }
  if (r.status === "error") {
    return <Message title="Eğitim yüklenemedi" text="Sunucuya ulaşılamıyor. Lütfen biraz sonra sayfayı yenileyin." />;
  }

  const { assignment, education, contents, summary } = r.data;
  const pct =
    summary.required_total > 0
      ? Math.round((summary.required_completed / summary.required_total) * 100)
      : summary.completed
        ? 100
        : 0;

  return (
    <div className="space-y-6">
      <div className="space-y-1">
        <p className="text-sm text-muted-foreground">Merhaba {assignment.personnel_name}</p>
        <h1 className="text-2xl font-semibold break-words">{education.name}</h1>
        <Badge variant="outline">{education.type_label}</Badge>
        {education.description && <p className="pt-2 text-sm whitespace-pre-wrap">{education.description}</p>}
      </div>

      <Card>
        <CardHeader>
          <CardTitle>İlerleme</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          <div className="flex justify-between text-sm">
            <span>
              Zorunlu içerikler: {summary.required_completed} / {summary.required_total}
            </span>
            <span className="text-muted-foreground">{assignment.status_label}</span>
          </div>
          <Progress value={pct} />
          {summary.completed && (
            <div className="flex items-center gap-2 rounded-md bg-emerald-100 p-3 text-sm font-medium text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">
              <CheckCircle2 className="size-5" aria-hidden />
              Eğitimi tamamladınız
            </div>
          )}
        </CardContent>
      </Card>

      {contents.length === 0 && <p className="text-sm text-muted-foreground">Bu eğitimde henüz içerik yok.</p>}
      <ol className="space-y-4">
        {[...contents]
          .sort((a, b) => a.sort_order - b.sort_order)
          .map((c) => {
            const { label, icon: Icon, Player } = learnKinds[c.kind];
            return (
              <li key={c.id}>
                <Card>
                  <CardHeader>
                    <CardTitle className="flex flex-wrap items-center gap-2">
                      <Icon className="size-5 text-muted-foreground" aria-hidden />
                      <span className="break-words">{c.title}</span>
                    </CardTitle>
                    <div className="flex flex-wrap gap-2">
                      <Badge variant="outline">{label}</Badge>
                      <Badge variant={c.is_required ? "default" : "secondary"}>
                        {c.is_required ? "Zorunlu" : "Opsiyonel"}
                      </Badge>
                      {c.progress.completed && (
                        <Badge className="bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">
                          <CheckCircle2 aria-hidden /> Tamamlandı
                        </Badge>
                      )}
                    </div>
                  </CardHeader>
                  <CardContent>
                    <Player token={token} content={c} />
                  </CardContent>
                </Card>
              </li>
            );
          })}
      </ol>
    </div>
  );
}
