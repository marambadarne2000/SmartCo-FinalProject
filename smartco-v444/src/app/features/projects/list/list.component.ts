import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatCardModule } from '@angular/material/card';
import { MatTableModule } from '@angular/material/table';
import { MatButtonModule } from '@angular/material/button';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { Router } from '@angular/router';
import { ApiService } from '../../../core/api.service';
import { CreateComponent as ProjectCreateComponent } from '../create/create.component';

@Component({
  selector: 'app-projects-list',
  standalone: true,
  imports: [
    CommonModule, MatCardModule, MatTableModule,
    MatButtonModule, MatProgressSpinnerModule, MatDialogModule
  ],
  templateUrl: './list.component.html',
  styleUrls: ['./list.component.scss']
})
export class ProjectsListComponent implements OnInit {
  private api = inject(ApiService);
  private dialog = inject(MatDialog);
  private router = inject(Router);

  displayedColumns = ['id', 'name', 'owner', 'created_at', 'actions'];
  data: any[] = [];
  loading = true;

  // يظهر زر الإنشاء فقط للأدمن (أو لمن يملك صلاحية create إن فعّلتها)
  canCreateProject = false;

  ngOnInit() {
    // حمّل هوية المستخدم لتحديد الدور/الصلاحية ثم حمّل المشاريع
    this.api.me().subscribe(me => {
      if (me.ok && me.data?.user) {
        const slug = me.data.user.role?.slug || '';
        // بالافتراضي نعتمد الدور
        this.canCreateProject = (slug === 'admin');

        // لو تفضّل الاعتماد على permissions (أزل التعليق):
        // this.canCreateProject = this.api.hasPermission('projects', 'create');
      }
      this.reload();
    });
  }

  reload() {
    this.loading = true;
    this.api.listProjects().subscribe({
      next: (res: any) => {
        this.data = res.data || [];
        this.loading = false;
      },
      error: (err) => {
        console.error('Error loading projects:', err);
        this.loading = false;
      }
    });
  }

  openCreate() {
    if (!this.canCreateProject) return;
    this.dialog.open(ProjectCreateComponent, { width: '600px' })
      .afterClosed()
      .subscribe((ok) => {
        if (ok) this.reload();
      });
  }

  /** الانتقال إلى صفحة مهام المشروع */
  viewTasks(projectId: number) {
    // حسب راوتك الحالية: /tasks/:project_id
    this.router.navigate(['/tasks', projectId]);
    // أو لو تستخدم Matrix params:
    // this.router.navigate(['/tasks', { project_id: projectId }]);
  }
}
