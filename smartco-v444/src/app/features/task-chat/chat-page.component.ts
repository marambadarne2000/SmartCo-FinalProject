import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { TaskChatPanelComponent } from './task-chat-panel.component';

@Component({
  standalone: true,
  selector: 'app-chat-page',
  imports: [CommonModule, RouterLink, TaskChatPanelComponent],
  template: `
    <div class="wrap">
      <div class="head">
<a routerLink="/tasks">⬅ Back to Tasks</a>
<h2>Task Conversation #{{ taskId }}</h2>
      </div>

      <app-task-chat-panel *ngIf="taskId" [taskId]="taskId"></app-task-chat-panel>
    </div>
  `,
  styles: [`
    .wrap { padding: 16px; }
    .head { display:flex; justify-content:space-between; align-items:center; margin-bottom: 12px; gap: 12px; flex-wrap: wrap; }
  `]
})
export class ChatPageComponent implements OnInit {
  private route = inject(ActivatedRoute);
  taskId!: number;

  ngOnInit(): void {
    this.taskId = Number(this.route.snapshot.paramMap.get('id')) || 0;
  }
}
