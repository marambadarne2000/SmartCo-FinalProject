import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, RouterModule } from '@angular/router';

import { MatTableModule } from '@angular/material/table';
import { MatButtonModule } from '@angular/material/button';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { MatIconModule } from '@angular/material/icon';
import { MatTooltipModule } from '@angular/material/tooltip';

import { ApiService } from '../../../core/api.service';
import { CreateTaskComponent } from '../create/create-task.component';

@Component({
  selector: 'app-tasks-list',
  standalone: true,
  imports: [
    CommonModule,
    RouterModule,                // لـ routerLink
    MatTableModule,
    MatButtonModule,
    MatProgressSpinnerModule,
    MatDialogModule,
    MatIconModule,               // أيقونة الشات
    MatTooltipModule             // تلميح الفأرة
  ],
  templateUrl: './list.component.html',
  styleUrls: ['./list.component.scss']
})
export class TasksListComponent implements OnInit {
  private api = inject(ApiService);
  private route = inject(ActivatedRoute);
  private dialog = inject(MatDialog);

  displayedColumns = ['id', 'name', 'assignee_name', 'status', 'due_date', 'chat', 'actions'];

  data: any[] = [];
  loading = true;
  projectId?: number;

  // الهوية/الصلاحيات
  myUserId?: number;
  isAdmin = false;
  canCreate = false; // New Task
  canManage = false; // Edit/Delete للأدمن فقط

  // حالة زر الإنهاء
  actingIds = new Set<number>();

  ngOnInit() {
    // احصل على بيانات المستخدم والصلاحيات ثم حمّل القائمة
    this.api.me().subscribe(me => {
      if (me.ok && me.data?.user) {
        this.myUserId = me.data.user.id;
        const slug = me.data.user.role?.slug || '';
        this.isAdmin = (slug === 'admin');

        // فقط الأدمن يرى New Task و Edit/Delete
        this.canCreate = this.isAdmin;
        this.canManage = this.isAdmin;
      }

      // بعد تحديد الصلاحيات، اربط بارام المشروع وحمّل البيانات
      this.route.paramMap.subscribe(params => {
        const pid = params.get('project_id');
        this.projectId = pid ? Number(pid) : undefined;
        this.reload();
      });
    });
  }

  reload() {
    this.loading = true;

    this.api.listTasks(this.projectId).subscribe({
      next: (res: any) => {
        const rows: any[] = res?.data ?? [];

        // 🔒 فلترة احترازية على الواجهة:
        // - الأدمن: يرى الكل
        // - غير الأدمن: فقط مهامي
        this.data = this.isAdmin
          ? rows
          : rows.filter(t => Number(t.assignee_id) === Number(this.myUserId));

        this.loading = false;
      },
      error: (err) => {
        console.error('Error loading tasks:', err);
        this.data = [];
        this.loading = false;
      }
    });
  }

  openCreateTask() {
    if (!this.canCreate) return;
    this.dialog.open(CreateTaskComponent, {
      width: '600px',
      data: { projectId: this.projectId ?? null }
    }).afterClosed().subscribe(ok => { if (ok) this.reload(); });
  }

  async deleteTask(t: any) {
    if (!this.canManage) return;
    if (!confirm(`Delete task #${t.id}?`)) return;
    try {
      await this.api.deleteTask(t.id);
      this.reload();
    } catch (e: any) {
      alert(e?.error?.error?.message || 'Delete failed');
    }
  }

  editTask(t: any) {
    if (!this.canManage) return;
    this.dialog.open(CreateTaskComponent, {
      width: '600px',
      data: { projectId: this.projectId ?? t.project_id ?? null, task: t }
    }).afterClosed().subscribe(ok => { if (ok) this.reload(); });
  }

  // يظهر زر الإنهاء للموظف إن كانت المهمة مُسندة له وليست منتهية
  canComplete(t: any): boolean {
    if (this.isAdmin) return false;          // الأدمن يدير عبر Edit/Delete
    if (!this.myUserId) return false;
    const isMine = Number(t.assignee_id) === Number(this.myUserId);
    const notDone = String(t.status) !== 'done';
    return isMine && notDone;
  }

  async markDone(t: any) {
    if (!this.canComplete(t)) return;
    try {
      this.actingIds.add(t.id);
      await this.api.completeTask(t.id);     // endpoint المخصص للإكمال
      this.reload();
    } catch (e: any) {
      const msg =
        e?.error?.error?.message ||
        e?.message ||
        'Failed to complete task';
      alert(msg);
    } finally {
      this.actingIds.delete(t.id);
    }
  }
}
