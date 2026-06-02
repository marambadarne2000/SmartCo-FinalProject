import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import {
  ApiService,
  ApiResp,
  ChatThreadListItem
} from '../../../core/api.service';

@Component({
  standalone: true,
  selector: 'app-chat-inbox',
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './inbox.component.html',
  styleUrls: ['./inbox.component.scss']
})
export class ChatInboxComponent implements OnInit {
  private api = inject(ApiService);

  loading = true;
  data: ChatThreadListItem[] = [];
  q = '';
  onlyUnread = false;

  ngOnInit(): void {
    this.load();
  }

  load() {
    this.loading = true;
    this.api
      .adminListChatThreads({
        q: this.q || undefined,
        unread_only: this.onlyUnread ? 1 : 0,
        limit: 30,
        offset: 0,
        order: this.onlyUnread ? 'unread' : 'latest'
      })
      .subscribe({
        next: (r: ApiResp<ChatThreadListItem[]>) => {
          this.data = r.ok ? r.data : [];
          this.loading = false;
        },
        error: () => {
          this.data = [];
          this.loading = false;
        }
      });
  }

  // ملخص آخر رسالة
  snippet(row: ChatThreadListItem): string {
    const t = (row.last_message_preview || '').trim();
    return t.length > 120 ? t.slice(0, 120) + '…' : t || '—';
  }

  // شارة عدد غير المقروء
  unreadBadge(n: number): string {
    return n > 99 ? '99+' : String(n || '');
  }

  // أسماء المشاركين بشكل مختصر
  participantsText(row: ChatThreadListItem): string {
    return (row.participants || []).map(p => p.name).join('، ');
  }
}
