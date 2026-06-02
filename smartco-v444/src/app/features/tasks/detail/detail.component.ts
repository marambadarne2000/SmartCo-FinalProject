import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, RouterLink } from '@angular/router';

import { ApiService } from '../../../core/api.service';
import { TaskChatPanelComponent } from '../../task-chat/task-chat-panel.component';

@Component({
  standalone: true,
  selector: 'app-task-detail',
  imports: [CommonModule, RouterLink, TaskChatPanelComponent],
  templateUrl: './detail.component.html',
  styleUrls: ['./detail.component.scss']
})
export class TaskDetailComponent implements OnInit {
  private route = inject(ActivatedRoute);
  private api = inject(ApiService);

  taskId!: number;
  task: any = null;
  loading = true;
  err: string | null = null;

  ngOnInit(): void {
    // قراءة :id من الـ Router
    this.taskId = Number(this.route.snapshot.paramMap.get('id')) || 0;
    this.load();
  }

  load() {
    this.loading = true;
    this.err = null;

    // إن كان لديك endpoint getTaskById استخدمه بدلاً من التالي.
    this.api.listTasks(undefined).subscribe({
      next: (r: any) => {
        if (r?.ok) {
          const rows = r.data || [];
          this.task = rows.find((x: any) => Number(x.id) === Number(this.taskId)) || null;
        } else {
          this.err = r?.error?.message || 'تعذر تحميل المهمة';
        }
        this.loading = false;
      },
      error: () => {
        this.err = 'تعذر تحميل المهمة';
        this.loading = false;
      }
    });
  }
}
