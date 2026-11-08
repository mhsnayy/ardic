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
};

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
