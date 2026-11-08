import "server-only";

// 02-mimari.md §3 sözleşmesinin TypeScript karşılığı.

export type Department = { id: number; name: string };
export type DepartmentWithCount = Department & { personnel_count: number };

export type Personnel = {
  id: number;
  first_name: string;
  last_name: string;
  full_name: string;
  email: string;
  department: Department;
};

export type PageMeta = { current_page: number; last_page: number; total: number };
export type Paginated<T> = { data: T[]; meta: PageMeta };

export type PersonnelSource = "live" | "stale" | "unavailable";

export type AssignmentStatus = "assigned" | "in_progress" | "completed";

export type EducationType = { value: string; label: string };

export type EducationStats = {
  total: number;
  assigned: number;
  in_progress: number;
  completed: number;
  completion_rate: number;
};

export type Education = {
  id: number;
  name: string;
  type: string;
  type_label: string;
  description: string | null;
  created_at: string;
  stats: EducationStats;
  contents_count: number;
};

export type LearningLinkInfo = { expires_at: string; created_at: string };

export type Assignment = {
  id: number;
  education_id: number;
  personnel_id: number;
  personnel_name: string;
  personnel_email: string;
  department_name: string | null;
  status: AssignmentStatus;
  status_label: string;
  assigned_at: string;
  completed_at: string | null;
  learning_link?: LearningLinkInfo | null;
};

export type PersonnelProgress = { total: number; completed: number; completion_rate: number };

export type PersonnelWithProgress = Personnel & { progress: PersonnelProgress };

export type PersonnelAssignment = Assignment & {
  education: { id: number; name: string; type: string; type_label: string };
};

export type AssignResult = {
  created: number[];
  already_assigned: number[];
  not_found: number[];
};

export type AuthLoginResponse = {
  token: string;
  expires_at: string;
  user: { id: number; name: string; email: string };
};

export type ContentKind = "video" | "document" | "link";

export type VideoProperties = { mime_type: string; size_bytes: number; duration_seconds: number | null };
export type DocumentProperties = { mime_type: string; size_bytes: number };
export type LinkProperties = { url: string };

export type Content = {
  id: number;
  education_id: number;
  kind: ContentKind;
  title: string;
  sort_order: number;
  is_required: boolean;
  properties: Partial<VideoProperties & DocumentProperties & LinkProperties>;
  created_at: string;
};

export type UploadKind = Exclude<ContentKind, "link">;

export type UploadTarget = {
  upload_id: number | string;
  upload_url: string;
  method: string;
  headers: Record<string, string>;
  expires_at: string;
  max_bytes: number;
};

export type LearningLink = { token: string; url: string; expires_at: string };

export type LearnProgress = {
  last_position_seconds: number;
  max_position_seconds: number;
  completed: boolean;
  completed_at: string | null;
};

export type LearnContent = {
  id: number;
  kind: ContentKind;
  title: string;
  is_required: boolean;
  sort_order: number;
  properties: Content["properties"];
  playback_url: string | null;
  progress: LearnProgress;
};

export type LearnSummary = { required_total: number; required_completed: number; completed: boolean };

export type Learning = {
  assignment: { id: number; status: AssignmentStatus; status_label: string; personnel_name: string };
  education: { id: number; name: string; type_label: string; description: string | null };
  contents: LearnContent[];
  summary: LearnSummary;
};

export type ProgressResult = {
  content_id: number;
  last_position_seconds: number;
  max_position_seconds: number;
  completed: boolean;
  assignment_status: AssignmentStatus;
  assignment_status_label: string;
  summary: LearnSummary;
};
