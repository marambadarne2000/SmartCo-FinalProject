// src/app/core/api.service.ts
import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders, HttpParams } from '@angular/common/http';
import { Observable, firstValueFrom, of, from } from 'rxjs';
import { map, catchError, switchMap } from 'rxjs/operators';

/* ===== Unified API response types ===== */
export type ApiOk<T> = { ok: true; data: T; meta?: Record<string, any> };
export type ApiErrorObject = { code: string; message: string; details?: any };
export type ApiErr = { ok: false; error: ApiErrorObject };
export type ApiResp<T> = ApiOk<T> | ApiErr;

export type Id = number;

/* ===== Core data types ===== */
export interface OverviewData {
  projects: number;
  tasks: number;
  done: number;
  in_progress: number;
  todo: number;
  overdue: number;
}

export interface UserLite {
  id: number;
  name: string;
  email: string;
}

export interface Permission {
  module: string;
  action: string;
}

export type TaskStatus = 'todo' | 'in_progress' | 'done';
export type TaskPriority = 'low' | 'medium' | 'high';

export interface MeData {
  user: null | {
    id: Id;
    name: string;
    email: string;
    status?: string;
    role?: { id: Id; slug: string; name: string };
  };
  permissions?: Permission[];
  byModule?: Record<string, string[]>;
  csrf?: string;
}

/* ===== Admin permissions types ===== */
export interface RoleRow {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  permissions: number[];
}

export interface PermRow {
  id: number;
  module: string;
  action: string;
  description: string | null;
}

/* ===== Notifications ===== */
export interface Notification {
  id: number;
  message: string;
  type: string;
  created_at: string;
  read: boolean;
}

/* ===== Task details ===== */
export interface TaskDetail {
  id: Id;
  project_id: Id;
  name: string;
  description: string | null;
  status: TaskStatus;
  priority?: TaskPriority | null;
  assignee_id: Id | null;
  assignee_name?: string | null;
  due_date: string | null;
  created_at: string;
  project_name?: string;
}

/* ===== Chat types ===== */
export interface ChatThread {
  id: Id;
  task_id: Id;
}

export interface ChatParticipant {
  user_id: Id;
  name: string;
  email: string;
  role_hint?: 'manager' | 'employee' | 'admin' | null;
}

export interface ChatThreadResp {
  thread: ChatThread;
  participants: ChatParticipant[];
}

export interface ChatMessage {
  id: Id;
  thread_id: Id;
  sender_id: Id;
  type: 'text' | 'file';
  text?: string | null;
  file_url?: string | null;
  created_at: string;
  read_by_me?: boolean;
}

export interface ChatMessagesResp {
  messages: ChatMessage[];
  paging: { has_more: boolean; next_before: Id | null };
}

/* ===== Chat inbox ===== */
export interface ChatInboxRow {
  thread_id: Id;
  task_id: Id;
  task_title?: string | null;
  task_name?: string | null;
  project_name?: string | null;
  last_text?: string | null;
  last_type: 'text' | 'file';
  last_sender_name?: string | null;
  last_at: string;
  unread_count: number;
  others_names?: string | null;
}

/* ===== Admin chat thread list ===== */
export interface ChatThreadListItem {
  thread_id: Id;
  task_id: Id;
  task_name: string;
  project_name?: string | null;
  last_message_preview: string | null;
  last_message_at: string;
  unread_count: number;
  participants: Array<{ id: Id; name: string }>;
  last_at?: string | number | Date;
  last_sender_name?: string | null;
  task_title?: string | null;
}

/* ===== Admin employee details ===== */
export interface AdminEmployeeDetails {
  id: number;
  name: string;
  email: string;
  status: string;
  role_slug: string;
  role_name: string;
  hourly_rate: number;
  max_active_tasks: number;
  started_at?: string | null;
  ended_at?: string | null;
}

/* ===== Admin employee attendance row ===== */
export interface AdminEmployeeAttendanceRow {
  date: string;
  day_name?: string | null;
  first_start?: string | null;
  last_end?: string | null;
  hours: number;
}

/* ===== Admin employee attendance summary ===== */
export interface AdminEmployeeAttendanceSummary {
  total_days: number;
  total_hours: number;
  estimated_pay: number;
  month: number;
  year: number;
}

/* ===== Admin employee attendance response ===== */
export interface AdminEmployeeAttendanceResponse {
  rows: AdminEmployeeAttendanceRow[];
  summary: AdminEmployeeAttendanceSummary;
}

/* ===== Admin employee personal profile ===== */
export interface AdminEmployeeProfile {
  id: number;
  name: string;
  email: string;
  status: string;
  role_slug: string;
  role_name: string;
  experience: string;
  bio: string;
  skills: string;
  notes: string;
  department: string;
  phone: string;
  address: string;
  cv: string;
  previous_jobs: string;
}

@Injectable({ providedIn: 'root' })
export class ApiService {
  private csrf: string | null = null;
  public permissions: Permission[] = [];

  constructor(private http: HttpClient) {}

  /* ------------ Internal helpers ------------ */
  private normalize<T>(r: any): ApiResp<T> {
    if (r && typeof r === 'object') {
      if ('ok' in r) {
        if (r.ok) return { ok: true, data: r.data as T, meta: r.meta ?? undefined };
        const e: ApiErrorObject =
          typeof r.error === 'object' && r.error
            ? {
                code: String(r.error.code || 'ERROR'),
                message: String(r.error.message || 'Unknown error'),
                details: r.error.details,
              }
            : { code: 'ERROR', message: String(r.error || 'Unknown error') };
        return { ok: false, error: e };
      }

      if ('success' in r) {
        return r.success
          ? ({ ok: true, data: r.data as T, meta: r.meta ?? undefined } as ApiOk<T>)
          : ({ ok: false, error: { code: 'ERROR', message: r.error?.message || r.error || 'Unknown error' } } as ApiErr);
      }
    }

    return { ok: false, error: { code: 'MALFORMED', message: 'Malformed response' } };
  }

  private mapTo<T>() {
    return map((r: any) => this.normalize<T>(r));
  }

  async ensureCsrf(force = false): Promise<string> {
    if (!force && this.csrf) return this.csrf;
    try {
      const r = await fetch('/api/csrf.php', { credentials: 'include' }).then(res => res.json());
      this.csrf = (r?.data?.csrf ?? r?.csrf ?? '') as string;
    } catch {
      this.csrf = '';
    }
    return this.csrf;
  }

  private jsonHeaders(token: string): HttpHeaders {
    return new HttpHeaders({
      'X-CSRF-Token': token,
      'Content-Type': 'application/json',
    });
  }

  private uploadHeaders(token: string): HttpHeaders {
    return new HttpHeaders({ 'X-CSRF-Token': token });
  }

  async resetPassword(email: string) {
    const token = await this.ensureCsrf();
    return firstValueFrom(
      this.http
        .post<any>(
          '/api/auth/forgot-password.php',
          { email },
          { withCredentials: true, headers: this.jsonHeaders(token) }
        )
        .pipe(
          map(response => {
            console.log('Reset response:', response);
            return this.normalize<{ message?: string }>(response);
          }),
          catchError(error => {
            console.error('Error in reset password:', error);
            return of({
              ok: false,
              error: {
                code: 'RESET_ERROR',
                message: error?.error?.error?.message || 'Something went wrong!',
                details: error
              }
            } as ApiErr);
          })
        )
    );
  }

  private toSqlDate(d?: Date | null): string | null {
    if (!d) return null;
    const pad = (n: number) => n.toString().padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  }

  /* ------------ Auth ------------ */
  async login(email: string, password: string) {
    const token = await this.ensureCsrf();
    return firstValueFrom(
      this.http
        .post<any>(
          '/api/auth/login.php',
          { email, password },
          { withCredentials: true, headers: this.jsonHeaders(token) }
        )
        .pipe(
          map(r => {
            const n = this.normalize<{ id: Id; name: string; email: string; role?: string; csrf?: string }>(r);
            if (n.ok && (n.data as any)?.csrf) this.csrf = (n.data as any).csrf;
            return n;
          })
        )
    );
  }

  me(): Observable<ApiResp<MeData>> {
    return this.http
      .get<any>('/api/auth/status.php', { withCredentials: true })
      .pipe(
        map(r => {
          const n = this.normalize<MeData>(r);
          if (n.ok) {
            if (n.data?.csrf) this.csrf = n.data.csrf;
            if (Array.isArray(n.data?.permissions)) this.permissions = n.data.permissions!;
          }
          return n;
        }),
        catchError(() =>
          from(this.ensureCsrf(true)).pipe(
            map(token => ({
              ok: true,
              data: { user: null, csrf: token } as MeData,
            }) as ApiOk<MeData>)
          )
        )
      );
  }

  async logout() {
    const token = await this.ensureCsrf();
    return firstValueFrom(
      this.http
        .post<any>('/api/auth/logout.php', {}, { withCredentials: true, headers: this.jsonHeaders(token) })
        .pipe(this.mapTo<{ logged_out: true }>())
    );
  }

  /* ------------ Permissions ------------ */
  getPermissions() {
    return this.http.get<any>('/api/auth/permissions.php', { withCredentials: true }).pipe(this.mapTo<Permission[]>());
  }

  async loadPermissions() {
    const resp = await firstValueFrom(
      this.getPermissions().pipe(
        catchError(() => of({ ok: false, error: { code: 'NET', message: 'network' } } as ApiErr))
      )
    );
    this.permissions = resp.ok ? resp.data : [];
  }

  hasPermission(module: string, action: string) {
    const m = module.toLowerCase();
    const a = action.toLowerCase();
    return this.permissions.some(p => p.module.toLowerCase() === m && p.action.toLowerCase() === a);
  }

  /* ------------ Users ------------ */
  listUsers() {
    return this.http.get<any>('/api/users/list.php', { withCredentials: true }).pipe(this.mapTo<UserLite[]>());
  }

  /* ------------ Notifications ------------ */
  listNotifications(limit = 20): Observable<ApiResp<Notification[]>> {
    const params = new HttpParams().set('limit', String(limit));
    return this.http
      .get<any>('/api/notifications/list.php', { params, withCredentials: true })
      .pipe(this.mapTo<Notification[]>());
  }

  async markNotificationAsRead(id: Id) {
    const token = await this.ensureCsrf();
    return firstValueFrom(
      this.http
        .post<any>('/api/notifications/read.php', { id }, { withCredentials: true, headers: this.jsonHeaders(token) })
        .pipe(this.mapTo<{ updated: true }>())
    );
  }

  /* ------------ Projects ------------ */
  listProjects(params?: {
    q?: string;
    mine?: 0 | 1;
    owner_id?: Id;
    due_from?: string;
    due_to?: string;
    limit?: number;
    offset?: number;
    dir?: 'asc' | 'desc';
  }): Observable<
    ApiResp<
      Array<{
        id: Id;
        name: string;
        description: string | null;
        owner_id: Id;
        owner_name: string;
        created_at: string;
        due_date: string | null;
      }>
    >
  > {
    let hp = new HttpParams();
    if (params) {
      for (const [k, v] of Object.entries(params)) {
        if (v !== undefined && v !== null && v !== '') hp = hp.set(k, String(v));
      }
    }
    return this.http.get<any>('/api/projects/list.php', { withCredentials: true, params: hp }).pipe(this.mapTo());
  }

  async createProject(payload: { name: string; description?: string; due_date?: string | null; members?: Id[] }) {
    const token = await this.ensureCsrf();
    return firstValueFrom(
      this.http
        .post<any>('/api/projects/create.php', payload, { withCredentials: true, headers: this.jsonHeaders(token) })
        .pipe(this.mapTo())
    );
  }

  async updateProject(payload: { id: Id; name?: string; description?: string | null; due_date?: string | null }) {
    const token = await this.ensureCsrf();
    return firstValueFrom(
      this.http
        .post<any>('/api/projects/update.php', payload, { withCredentials: true, headers: this.jsonHeaders(token) })
        .pipe(this.mapTo())
    );
  }

  async deleteProject(id: Id) {
    const token = await this.ensureCsrf();
    return firstValueFrom(
      this.http
        .post<any>('/api/projects/delete.php', { id }, { withCredentials: true, headers: this.jsonHeaders(token) })
        .pipe(this.mapTo())
    );
  }

  /* ------------ Tasks ------------ */
  listTasks(projectId?: Id) {
    const url = projectId ? `/api/tasks/list.php?project_id=${projectId}` : '/api/tasks/list.php';
    return this.http.get<any>(url, { withCredentials: true }).pipe(this.mapTo());
  }

  async createTask(payload: {
    project_id: Id;
    title?: string;
    name?: string;
    description?: string;
    due_date?: string | null;
    assignee_id?: Id | null;
    status?: TaskStatus;
    priority?: TaskPriority;
  }) {
    const token = await this.ensureCsrf();
    const body = { ...payload, title: payload.title ?? payload.name };
    return firstValueFrom(
      this.http
        .post<any>('/api/tasks/create.php', body, { withCredentials: true, headers: this.jsonHeaders(token) })
        .pipe(this.mapTo())
    );
  }

  async deleteTask(id: Id) {
    const token = await this.ensureCsrf();
    return firstValueFrom(
      this.http
        .post<any>('/api/tasks/delete.php', { id }, { withCredentials: true, headers: this.jsonHeaders(token) })
        .pipe(this.mapTo())
    );
  }

  async updateTask(payload: {
    id: Id;
    title?: string;
    name?: string;
    description?: string;
    status?: TaskStatus;
    assignee_id?: Id | null;
    due_date?: string | null;
    priority?: TaskPriority;
  }) {
    const token = await this.ensureCsrf();
    const body = { ...payload, title: payload.title ?? payload.name };
    return firstValueFrom(
      this.http
        .post<any>('/api/tasks/update.php', body, { withCredentials: true, headers: this.jsonHeaders(token) })
        .pipe(this.mapTo())
    );
  }

  async completeTask(id: Id) {
    const token = await this.ensureCsrf();
    return firstValueFrom(
      this.http
        .post<any>('/api/tasks/complete.php', { id }, { withCredentials: true, headers: this.jsonHeaders(token) })
        .pipe(this.mapTo<{ updated: boolean; id: Id }>())
    );
  }

  getTaskById(id: Id) {
    const params = new HttpParams().set('id', String(id));
    return this.http
      .get<any>('/api/tasks/get.php', { withCredentials: true, params })
      .pipe(this.mapTo<TaskDetail>());
  }

  /* ------------ Reports / Dashboard ------------ */
  getDashboardOverview(projectId?: Id) {
    const params = projectId != null ? new HttpParams().set('project_id', String(projectId)) : undefined;
    return this.http
      .get<any>('/api/reports/overview.php', { params, withCredentials: true })
      .pipe(this.mapTo<OverviewData>());
  }

  getTasksByStatus(projectId?: Id) {
    const params = projectId != null ? new HttpParams().set('project_id', String(projectId)) : undefined;
    return this.http
      .get<any>('/api/reports/tasks_by_status.php', { params, withCredentials: true })
      .pipe(this.mapTo<Array<{ label: string; count: number }>>());
  }

  getTasksPerProject(limit = 10) {
    const params = new HttpParams().set('limit', String(limit));
    return this.http
      .get<any>('/api/reports/tasks_per_project.php', { params, withCredentials: true })
      .pipe(this.mapTo<Array<{ id: Id; project: string; cnt: number }>>());
  }

  getTasksByPriority(projectId?: Id) {
    const params = projectId != null ? new HttpParams().set('project_id', String(projectId)) : undefined;
    return this.http
      .get<any>('/api/reports/tasks_by_priority.php', { params, withCredentials: true })
      .pipe(this.mapTo<Array<{ label: string; count: number }>>());
  }

  /* ------------ Admin: Permissions ------------ */
  adminGetRolesWithPerms() {
    return this.http
      .get<any>('/api/admin/permissions/index.php', { withCredentials: true })
      .pipe(this.mapTo<{ roles: RoleRow[]; permissions: PermRow[] }>());
  }

  async adminUpdateRolePerms(roleId: number, permissions: number[]) {
    const token = await this.ensureCsrf();
    return firstValueFrom(
      this.http
        .post<any>(
          '/api/admin/permissions/index.php',
          { role_id: roleId, permissions },
          { withCredentials: true, headers: this.jsonHeaders(token) }
        )
        .pipe(this.mapTo<{ updated: true; role_id: number; count: number }>())
    );
  }

  async sendNotification(payload: {
    title: string;
    message: string;
    link?: string;
    role: 'all' | 'admin' | 'manager';
  }) {
    const token = await this.ensureCsrf();
    return firstValueFrom(
      this.http
        .post<any>('/api/notifications/send.php', payload, { withCredentials: true, headers: this.jsonHeaders(token) })
        .pipe(this.mapTo<{ sent_to: number }>())
    );
  }

  /* ------------ Admin: Employees list ------------ */
adminListEmployees(params?: { year?: number; month?: number; status?: 'active' | 'all' }) {
  let hp = new HttpParams();

  if (params?.year) hp = hp.set('year', String(params.year));
  if (params?.month) hp = hp.set('month', String(params.month));
  if (params?.status) hp = hp.set('status', String(params.status));

  return this.http
    .get<any>('/api/admin/employees/list.php', { withCredentials: true, params: hp })
    .pipe(
      this.mapTo<
        Array<{
          id: number;
          name: string;
          email: string;
          status: string;
          role_slug: string;
          role_name: string;
          hourly_rate: number;
          max_active_tasks: number;
          active_tasks: number;
          hours_month: number;
          month: number;
          year: number;
        }>
      >()
    );
}

  async adminUpdateEmployeeSettings(payload: {
    user_id: number;
    hourly_rate?: number;
    max_active_tasks?: number;
  }) {
    const token = await this.ensureCsrf();
    return firstValueFrom(
      this.http
        .post<any>('/api/admin/employees/update.php', payload, { withCredentials: true, headers: this.jsonHeaders(token) })
        .pipe(this.mapTo<{ updated: boolean; user_id: number }>())
    );
  }

  /* ------------ Admin: Employee details page ------------ */
  adminGetEmployeeDetails(userId: number) {
    const hp = new HttpParams().set('user_id', String(userId));
    return this.http
      .get<any>('/api/admin/employees/details.php', { withCredentials: true, params: hp })
      .pipe(this.mapTo<AdminEmployeeDetails>());
  }

  adminGetEmployeeAttendance(params: { user_id: number; year: number; month: number }) {
    const hp = new HttpParams()
      .set('user_id', String(params.user_id))
      .set('year', String(params.year))
      .set('month', String(params.month));

    return this.http
      .get<any>('/api/admin/employees/attendance.php', { withCredentials: true, params: hp })
      .pipe(this.mapTo<AdminEmployeeAttendanceResponse>());
  }

  /* ------------ Admin: Employee personal profile ------------ */
  adminGetEmployeeProfile(userId: number) {
    const hp = new HttpParams().set('user_id', String(userId));
    return this.http
      .get<any>('/api/admin/employees/profile.php', { withCredentials: true, params: hp })
      .pipe(this.mapTo<AdminEmployeeProfile>());
  }

  // Upload an employee CV file (multipart/form-data).
async adminUploadEmployeeCv(userId: number, file: File) {
  const token = await this.ensureCsrf();

  const form = new FormData();
  form.append('user_id', String(userId));
  form.append('cv', file);

  return firstValueFrom(
    this.http
      .post<any>('/api/admin/employees/upload_cv.php', form, {
        withCredentials: true,
        headers: new HttpHeaders({ 'X-CSRF-Token': token }),
      })
      .pipe(this.mapTo<{ user_id: number; file_name: string; file_url: string }>())
  );
}

// Update employee status from Company Crew page.
async adminUpdateEmployeeStatus(userId: number, status: 'active' | 'inactive' | 'banned') {
  const token = await this.ensureCsrf();

  return firstValueFrom(
    this.http
      .post<any>(
        '/api/admin/employees/update.php',
        { user_id: userId, status },
        { withCredentials: true, headers: this.jsonHeaders(token) }
      )
      .pipe(this.mapTo<{ updated: boolean; user_id: number; updated_fields: string[] }>())
  );
}

  /* ------------ Chat (Task Threads) ------------ */
  getChatThreadByTask(taskId: Id): Observable<ApiResp<ChatThreadResp>> {
    const params = new HttpParams().set('task_id', String(taskId));
    return this.http
      .get<any>('/api/task_thread.php', { withCredentials: true, params })
      .pipe(this.mapTo<ChatThreadResp>());
  }

  getChatThreadById(threadId: Id): Observable<ApiResp<ChatThreadResp>> {
    const params = new HttpParams().set('thread_id', String(threadId));
    return this.http
      .get<any>('/api/task_thread.php', { withCredentials: true, params })
      .pipe(this.mapTo<ChatThreadResp>());
  }

  getChatMessages(threadId: Id, before?: Id, limit = 50): Observable<ApiResp<ChatMessagesResp>> {
    let params = new HttpParams().set('thread_id', String(threadId)).set('limit', String(limit));
    if (before) params = params.set('before', String(before)).set('before_id', String(before));
    return this.http
      .get<any>('/api/messages.php', { withCredentials: true, params })
      .pipe(this.mapTo<ChatMessagesResp>());
  }

  async sendChatText(threadId: Id, text: string) {
    const token = await this.ensureCsrf();
    const form = new FormData();
    form.append('thread_id', String(threadId));
    form.append('type', 'text');
    form.append('text', text);
    form.append('message', text);
    form.append('body', text);
    form.append('content', text);

    return firstValueFrom(
      this.http
        .post<any>('/api/send_message.php', form, {
          withCredentials: true,
          headers: new HttpHeaders({ 'X-CSRF-Token': token }),
        })
        .pipe(this.mapTo<ChatMessage>())
    );
  }

  async sendChatFile(threadId: Id, file: File) {
    const token = await this.ensureCsrf();
    const form = new FormData();
    form.append('thread_id', String(threadId));
    form.append('type', 'file');
    form.append('file', file);
    form.append('attachment', file);

    return firstValueFrom(
      this.http
        .post<any>('/api/send_message.php', form, {
          withCredentials: true,
          headers: this.uploadHeaders(token),
        })
        .pipe(this.mapTo<ChatMessage>())
    );
  }

  async markChatRead(threadId: Id, upToMessageId: Id) {
    const token = await this.ensureCsrf();
    const payload: any = {
      thread_id: threadId,
      up_to_message_id: upToMessageId,
      last_id: upToMessageId,
    };

    return firstValueFrom(
      this.http
        .post<any>('/api/mark_read.php', payload, {
          withCredentials: true,
          headers: this.jsonHeaders(token),
        })
        .pipe(this.mapTo<{ ok: boolean }>())
    );
  }

  getChatInbox(params?: {
    q?: string;
    only_unread?: 0 | 1;
    limit?: number;
    offset?: number;
  }): Observable<ApiResp<ChatInboxRow[]>> {
    let hp = new HttpParams();
    if (params) {
      for (const [k, v] of Object.entries(params)) {
        if (v !== undefined && v !== null && v !== '') hp = hp.set(k, String(v));
      }
    }
    return this.http
      .get<any>('/api/chat/inbox.php', { withCredentials: true, params: hp })
      .pipe(this.mapTo<ChatInboxRow[]>());
  }

  adminListChatThreads(params?: {
    q?: string;
    unread_only?: 0 | 1;
    mine?: 0 | 1;
    limit?: number;
    offset?: number;
    order?: 'latest' | 'unread';
  }): Observable<ApiResp<ChatThreadListItem[]>> {
    let hp = new HttpParams();
    if (params) {
      for (const [k, v] of Object.entries(params)) {
        if (v !== undefined && v !== null && v !== '') hp = hp.set(k, String(v));
      }
    }
    return this.http
      .get<any>('/api/chat/inbox.php', { withCredentials: true, params: hp })
      .pipe(this.mapTo<ChatThreadListItem[]>());
  }

  countMyUnread(): Observable<ApiResp<{ unread: number }>> {
    return this.http
      .get<any>('/api/chat/unread_count.php', { withCredentials: true })
      .pipe(this.mapTo<{ unread: number }>());
  }

  /* ------------ Time tracking ------------ */
  clockIn() {
    return this.http
      .post<any>('/api/time/clock_in.php', {}, {
        withCredentials: true,
        headers: this.jsonHeaders(this.csrf || '')
      })
      .pipe(this.mapTo<{ clocked_in: boolean; session_id?: number }>());
  }

  clockOut() {
    return this.http
      .post<any>('/api/time/clock_out.php', {}, {
        withCredentials: true,
        headers: this.jsonHeaders(this.csrf || '')
      })
      .pipe(this.mapTo<{ clocked_out: boolean; affected: number }>());
  }

  heartbeat() {
    return this.http
      .get<any>('/api/time/heartbeat.php', { withCredentials: true })
      .pipe(this.mapTo<{ has_open: boolean; session_id?: number }>());
  }
}