import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatListModule } from '@angular/material/list';
import { MatButtonModule } from '@angular/material/button';
import { ApiService, ApiResp } from '../../core/api.service';

export interface Notification {
  id: number;
  message: string;
  type: string;
  read: boolean;
  created_at: string;
  link?: string; // עדכון נוסף
}

@Component({
  selector: 'app-notifications',
  standalone: true,
  imports: [CommonModule, MatCardModule, MatIconModule, MatListModule, MatButtonModule],
  templateUrl: './notifications.component.html',
  styleUrls: ['./notifications.component.scss']
})
export class NotificationsComponent implements OnInit {
  private api = inject(ApiService);

  notifications: Notification[] = [];
  loading = true;
  error: string | null = null;

  ngOnInit() {
    this.load();
  }

  load() {
    this.loading = true;
    this.api.listNotifications().subscribe({
      next: (res: ApiResp<Notification[]>) => {
        if (res.ok) {
          // מוודא שיש את השדה link בכל הודעה
          this.notifications = res.data.map(n => ({
            ...n,
            link: n.link !== undefined ? n.link : undefined
          }));
        } else {
          this.error = res.error.message || 'Error loading notifications';
        }
        this.loading = false;
      },
      error: () => {
        this.error = 'Network error';
        this.loading = false;
      }
    });
  }

  async markAsRead(id: number) {
    try {
      const resp = await this.api.markNotificationAsRead(id);
      if (resp.ok) {
        this.notifications = this.notifications.map(n =>
          n.id === id ? { ...n, read: true } : n
        );
      }
    } catch {
      alert('Network error');
    }
  }
}
