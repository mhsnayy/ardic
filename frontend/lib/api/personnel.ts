import "server-only";
import { apiFetch, readApi } from "./client";
import type { DepartmentWithCount, Paginated, Personnel } from "./types";

export type PersonnelInput = {
  first_name: string;
  last_name: string;
  email: string;
  department_id: number;
};

export function listPersonnel(params: { search?: string; department_id?: string; page?: string }) {
  return readApi<Paginated<Personnel>>("personnel", "/api/admin/personnel", params);
}

export function createPersonnel(input: PersonnelInput) {
  return apiFetch<{ data: Personnel }>("personnel", "/api/admin/personnel", { method: "POST", body: input });
}

export function updatePersonnel(id: number, input: PersonnelInput) {
  return apiFetch<{ data: Personnel }>("personnel", `/api/admin/personnel/${id}`, { method: "PUT", body: input });
}

export function deletePersonnel(id: number) {
  return apiFetch<void>("personnel", `/api/admin/personnel/${id}`, { method: "DELETE" });
}

export function listAdminDepartments() {
  return readApi<{ data: DepartmentWithCount[] }>("personnel", "/api/admin/departments");
}

export function createDepartment(name: string) {
  return apiFetch<unknown>("personnel", "/api/admin/departments", { method: "POST", body: { name } });
}

export function updateDepartment(id: number, name: string) {
  return apiFetch<unknown>("personnel", `/api/admin/departments/${id}`, { method: "PUT", body: { name } });
}

export function deleteDepartment(id: number) {
  return apiFetch<void>("personnel", `/api/admin/departments/${id}`, { method: "DELETE" });
}
