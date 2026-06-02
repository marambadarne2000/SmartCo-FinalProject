// src/app/features/tasks/create/create-task.component.ts
import { Component, Inject, OnInit, inject, signal, computed, DestroyRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  FormBuilder,
  ReactiveFormsModule,
  Validators,
  AbstractControl,
  ValidationErrors,
} from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatNativeDateModule } from '@angular/material/core';
import { MatSelectModule } from '@angular/material/select';
import { ApiService, ApiResp, UserLite } from '../../../core/api.service';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';

type CreateTaskData = { projectId: number | null; task?: any };
type TaskStatus = 'todo' | 'in_progress' | 'done';
type TaskPriority = 'low' | 'medium' | 'high';

const STATUS_VALUES: TaskStatus[] = ['todo', 'in_progress', 'done'];
const PRIORITY_VALUES: TaskPriority[] = ['low', 'medium', 'high'];

function toSqlDate(d?: Date | null): string | null {
  if (!d) return null;
  const pad = (n: number) => String(n).toString().padStart(2, '0');
  const copy = new Date(d);
  copy.setHours(0, 0, 0, 0);
  return `${copy.getFullYear()}-${pad(copy.getMonth() + 1)}-${pad(copy.getDate())}`;
}

function fromSqlDate(s?: string | null): Date | null {
  if (!s) return null;
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s);
  if (!m) return null;
  const y = Number(m[1]),
    mo = Number(m[2]),
    d = Number(m[3]);
  const dt = new Date(y, mo - 1, d);
  dt.setHours(0, 0, 0, 0);
  return dt;
}

function noWhitespace(ctrl: AbstractControl): ValidationErrors | null {
  const v = (ctrl.value ?? '').toString();
  return v.trim().length === 0 ? { whitespace: true } : null;
}

@Component({
  selector: 'app-create-task',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatDatepickerModule,
    MatNativeDateModule,
    MatSelectModule,
  ],
  templateUrl: './create-task.component.html',
  styleUrls: ['./create-task.component.scss'],
})
export class CreateTaskComponent implements OnInit {
  private fb = inject(FormBuilder);
  private api = inject(ApiService);
  private ref = inject(MatDialogRef<CreateTaskComponent>);
  private destroyRef = inject(DestroyRef);

  constructor(@Inject(MAT_DIALOG_DATA) public data: CreateTaskData) {}

  users = signal<UserLite[]>([]);
  loading = signal(false);
  loadingUsers = signal(true);
  isEdit = computed<boolean>(() => !!this.data?.task);
  errorMsg = signal<string | null>(null);

  form = this.fb.group({
    project_id: [
      this.data?.projectId ?? this.data?.task?.project_id ?? null,
      [Validators.required],
    ],
    name: ['', [Validators.required, Validators.minLength(3), noWhitespace]],
    description: [''],
    due_date: [null as Date | null],
    assignee_id: [null as number | null],
    priority: ['medium' as TaskPriority],
    status: ['todo' as TaskStatus],
  });

  dateNotPast = (d: Date | null) => {
    if (!d) return true;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const dt = new Date(d);
    dt.setHours(0, 0, 0, 0);
    return dt >= today;
  };

  ngOnInit() {
    this.api
      .listUsers()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (r: ApiResp<UserLite[]>) => {
          this.users.set(r.ok ? r.data ?? [] : []);
          this.loadingUsers.set(false);
        },
        error: () => {
          this.users.set([]);
          this.loadingUsers.set(false);
        },
      });

    if (this.data?.projectId || this.isEdit()) {
      this.form.controls.project_id.disable({ onlySelf: true });
    }

    if (this.data?.task) {
      const t = this.data.task ?? {};
      const status: TaskStatus = STATUS_VALUES.includes(t.status) ? t.status : 'todo';
      const priority: TaskPriority = PRIORITY_VALUES.includes(t.priority) ? t.priority : 'medium';

      this.form.patchValue(
        {
          project_id: this.data.projectId ?? t.project_id ?? null,
          name: (t.name ?? t.title ?? '').toString(),
          description: (t.description ?? '').toString(),
          due_date: fromSqlDate(t.due_date),
          assignee_id: t.assignee_id ?? null,
          priority,
          status,
        },
        { emitEvent: false },
      );
    }
  }

  assigneeName(): string {
    const id = this.form.getRawValue().assignee_id as number | null;
    if (id == null) return 'Unassigned';
    const u = this.users().find((x) => x.id === id);
    return u?.name ?? 'Unassigned';
  }

  async save() {
    this.errorMsg.set(null);
    if (this.loading() || this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const dueDate = this.form.getRawValue().due_date as Date | null;
    if (dueDate) {
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      const d = new Date(dueDate);
      d.setHours(0, 0, 0, 0);
      if (d < today) {
        this.errorMsg.set('Due date cannot be in the past');
        return;
      }
    }

    this.loading.set(true);
    this.form.disable({ emitEvent: false });

    try {
      const raw = this.form.getRawValue() as {
        project_id: number | null;
        name: string;
        description?: string;
        due_date: Date | null;
        assignee_id: number | null;
        priority: TaskPriority;
        status: TaskStatus;
      };

      const payload = {
        project_id: Number(raw.project_id),
        name: raw.name.trim(),
        description: (raw.description ?? '').trim(),
        due_date: toSqlDate(raw.due_date),
        assignee_id: raw.assignee_id ?? null,
        priority: PRIORITY_VALUES.includes(raw.priority) ? raw.priority : 'medium',
        status: STATUS_VALUES.includes(raw.status) ? raw.status : 'todo',
      };

      if (!payload.project_id || payload.project_id < 1) {
        this.errorMsg.set('Project ID is required');
        return;
      }
      if (!payload.name) {
        this.errorMsg.set('Title is required');
        return;
      }

      if (this.isEdit()) {
        const resp = await this.api.updateTask({ id: this.data!.task.id, ...payload });
        if (!resp.ok) {
          this.errorMsg.set(resp.error?.message || 'Failed to update task');
          return;
        }
      } else {
        const resp = await this.api.createTask(payload as any);
        if (!resp.ok) {
          this.errorMsg.set(resp.error?.message || 'Failed to create task');
          return;
        }
      }

      this.ref.close(true);
    } catch (e: any) {
      const msg =
        e?.error?.error?.message ||
        (typeof e?.error === 'string' ? e.error : '') ||
        'Network error. Please try again.';
      this.errorMsg.set(msg);
    } finally {
      this.loading.set(false);
      try {
        this.form.enable({ emitEvent: false });
        if (this.data?.projectId || this.isEdit()) {
          this.form.controls.project_id.disable({ onlySelf: true });
        }
      } catch {
      }
    }
  }

  cancel() {
    this.ref.close(false);
  }
}
