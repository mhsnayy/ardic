import "server-only";
import { apiFetch, readApi } from "./client";
import type {
  AssignResult,
  Assignment,
  Content,
  LearningLink,
  UploadKind,
  UploadTarget,
  AssignmentStatus,
  Department,
  Education,
  EducationType,
  Paginated,
  PersonnelAssignment,
  PersonnelProgress,
  PersonnelSource,
  PersonnelWithProgress,
  Personnel,
} from "./types";

export type EducationInput = { name: string; type: string; description?: string };

export function listEducationTypes() {
  return readApi<{ data: EducationType[] }>("education", "/api/education-types");
}

export function listEducations(params: { search?: string; page?: string | number }) {
  return readApi<Paginated<Education>>("education", "/api/educations", params);
}

export function getEducation(id: string) {
  return readApi<{ data: Education }>("education", `/api/educations/${encodeURIComponent(String(id))}`);
}

export function createEducation(input: EducationInput) {
  return apiFetch<{ data: Education }>("education", "/api/educations", { method: "POST", body: input });
}

export function updateEducation(id: number, input: EducationInput) {
  return apiFetch<{ data: Education }>("education", `/api/educations/${encodeURIComponent(String(id))}`, { method: "PUT", body: input });
}

export function deleteEducation(id: number) {
  return apiFetch<void>("education", `/api/educations/${encodeURIComponent(String(id))}`, { method: "DELETE" });
}

export function listAssignments(educationId: string) {
  return readApi<{ data: Assignment[] }>("education", `/api/educations/${encodeURIComponent(String(educationId))}/assignments`);
}

export function assignPersonnel(educationId: number, personnelIds: number[]) {
  return apiFetch<AssignResult>("education", `/api/educations/${encodeURIComponent(String(educationId))}/assignments`, {
    method: "POST",
    body: { personnel_ids: personnelIds },
  });
}

export function updateAssignmentStatus(id: number, status: AssignmentStatus) {
  return apiFetch<{ data: Assignment }>("education", `/api/assignments/${encodeURIComponent(String(id))}`, {
    method: "PATCH",
    body: { status },
  });
}

export function deleteAssignment(id: number) {
  return apiFetch<void>("education", `/api/assignments/${encodeURIComponent(String(id))}`, { method: "DELETE" });
}

export type PersonnelListResponse = {
  data: PersonnelWithProgress[];
  meta: { personnel_source: PersonnelSource };
};

export function listPersonnelProgress(params: { search?: string; department_id?: string | number }) {
  return readApi<PersonnelListResponse>("education", "/api/personnel", params);
}

/** Server Action içinde kullanım için (hata fırlatır). */
export function fetchPersonnelByDepartment(departmentId: number) {
  return apiFetch<PersonnelListResponse>("education", "/api/personnel", {
    query: { department_id: departmentId },
  });
}

export type PersonnelAssignmentsResponse = {
  data: PersonnelAssignment[];
  meta: {
    personnel_source: PersonnelSource;
    personnel: Personnel | null;
    progress: PersonnelProgress & { assigned: number; in_progress: number };
  };
};

export function listPersonnelAssignments(personnelId: string) {
  return readApi<PersonnelAssignmentsResponse>("education", `/api/personnel/${encodeURIComponent(String(personnelId))}/assignments`);
}

export function listEpDepartments() {
  return readApi<{ data: Department[]; meta: { personnel_source: PersonnelSource } }>(
    "education",
    "/api/departments",
  );
}

const eid = (id: number | string) => encodeURIComponent(String(id));

export function listContents(educationId: string) {
  return readApi<{ data: Content[] }>("education", `/api/educations/${eid(educationId)}/contents`);
}

export function requestUpload(
  educationId: number,
  input: { kind: UploadKind; filename: string; mime_type: string; size_bytes: number },
) {
  return apiFetch<UploadTarget>("education", `/api/educations/${eid(educationId)}/uploads`, {
    method: "POST",
    body: input,
  });
}

export type ContentInput =
  | { type: "video"; title: string; is_required: boolean; upload_id: number | string; duration_seconds: number }
  | { type: "document"; title: string; is_required: boolean; upload_id: number | string }
  | { type: "link"; title: string; is_required: boolean; url: string };

export function createContent(educationId: number, input: ContentInput) {
  return apiFetch<{ data: Content }>("education", `/api/educations/${eid(educationId)}/contents`, {
    method: "POST",
    body: input,
  });
}

export function reorderContents(educationId: number, ids: number[]) {
  return apiFetch<{ data: Content[] }>("education", `/api/educations/${eid(educationId)}/contents/order`, {
    method: "PATCH",
    body: { ids },
  });
}

export function deleteContent(id: number) {
  return apiFetch<void>("education", `/api/contents/${eid(id)}`, { method: "DELETE" });
}

export function createLearningLink(assignmentId: number) {
  return apiFetch<LearningLink>("education", `/api/assignments/${eid(assignmentId)}/learning-link`, { method: "POST" });
}

export function revokeLearningLink(assignmentId: number) {
  return apiFetch<void>("education", `/api/assignments/${eid(assignmentId)}/learning-link`, { method: "DELETE" });
}
