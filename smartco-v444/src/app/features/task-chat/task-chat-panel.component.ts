import {
  Component, Input, OnDestroy, OnInit, OnChanges, SimpleChanges,
  inject
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import {
  ApiService, ChatMessage, ChatThreadResp, Id
} from '../../core/api.service';
import {
  Subscription, interval, switchMap, filter, Subject, takeUntil, catchError, of
} from 'rxjs';

@Component({
  standalone: true,
  selector: 'app-task-chat-panel',
  imports: [CommonModule, FormsModule],
  templateUrl: './task-chat-panel.component.html',
  styleUrls: ['./task-chat-panel.component.css'],
})
export class TaskChatPanelComponent implements OnInit, OnDestroy, OnChanges {
  @Input({ required: true }) taskId!: Id;

  private api = inject(ApiService);

  threadId?: Id;
  participants: ChatThreadResp['participants'] = [];
  messages: ChatMessage[] = [];

  loading = false;
  sending = false;
  loadErr: string | null = null;

  hasMore = true;
  nextBefore: Id | null = null;

  text = '';
  file?: File | null;

  private pollSub?: Subscription;
  private destroyed$ = new Subject<void>();

  myId: Id | null = null;
  private markReadTimer?: any;

  ngOnInit(): void {
    this.api.me().pipe(takeUntil(this.destroyed$)).subscribe(m => {
      if (m.ok && m.data.user) this.myId = m.data.user.id;
    });

    this.bootstrap();
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['taskId'] && !changes['taskId'].firstChange) {
      this.resetState();
      this.bootstrap();
    }
  }

  ngOnDestroy(): void {
    this.destroyed$.next();
    this.destroyed$.complete();
    this.stopPolling();
    if (this.markReadTimer) clearTimeout(this.markReadTimer);
  }

  private bootstrap() {
    if (!this.taskId) return;
    this.initThread();
  }

  private resetState() {
    this.stopPolling();
    this.threadId = undefined;
    this.participants = [];
    this.messages = [];
    this.hasMore = true;
    this.nextBefore = null;
    this.text = '';
    this.file = null;
    this.loading = false;
    this.loadErr = null;
  }

  private stopPolling() {
    this.pollSub?.unsubscribe();
    this.pollSub = undefined;
  }

  private initThread() {
    this.loading = true;
    this.loadErr = null;

    this.api.getChatThreadByTask(this.taskId)
      .pipe(
        takeUntil(this.destroyed$),
        catchError(err => {
          this.loadErr = err?.error?.error?.message || 'فشل تحميل القناة';
          return of({ ok: false } as any);
        })
      )
      .subscribe((resp) => {
        if (!resp || !resp.ok) { this.loading = false; return; }

        this.threadId = resp.data.thread.id;
        this.participants = resp.data.participants;
        this.loadLatest();

        this.stopPolling();
        this.pollSub = interval(4000)
          .pipe(
            takeUntil(this.destroyed$),
            filter(() => !!this.threadId),
            switchMap(() => this.api.getChatMessages(this.threadId!, undefined, 30)),
            catchError(() => of({ ok: false } as any))
          )
          .subscribe((r) => {
            if (r && r.ok) {
              this.mergeNewMessages(r.data.messages);
              this.throttledMarkRead();
            }
          });

        this.loading = false;
      });
  }

  loadLatest() {
    if (!this.threadId) return;
    this.api.getChatMessages(this.threadId, undefined, 50)
      .pipe(takeUntil(this.destroyed$))
      .subscribe((r) => {
        if (!r.ok) return;
        this.messages = r.data.messages;
        this.hasMore = r.data.paging.has_more;
        this.nextBefore = r.data.paging.next_before;
        this.throttledMarkRead();
        setTimeout(() => this.scrollToBottom(), 0);
      });
  }

  loadOlder() {
    if (!this.threadId || !this.hasMore || !this.nextBefore) return;
    this.api.getChatMessages(this.threadId, this.nextBefore, 50)
      .pipe(takeUntil(this.destroyed$))
      .subscribe((r) => {
        if (!r.ok) return;
        this.messages = [...r.data.messages, ...this.messages];
        this.hasMore = r.data.paging.has_more;
        this.nextBefore = r.data.paging.next_before;
      });
  }

  async sendText() {
    const body = this.text.trim();
    if (!body || !this.threadId || this.sending) return;
    this.sending = true;
    try {
      const r = await this.api.sendChatText(this.threadId, body);
      if (r.ok) {
        this.text = '';
        this.mergeNewMessages([r.data]);
        setTimeout(() => this.scrollToBottom(), 0);
        this.throttledMarkRead();
      } else {
        alert(r.error.message || 'تعذّر إرسال الرسالة');
      }
    } catch (err: any) {
      alert(err?.error?.error?.message || 'تعذّر إرسال الرسالة');
    } finally {
      this.sending = false;
    }
  }

  onFilePicked(ev: Event) {
    const input = ev.target as HTMLInputElement;
    this.file = input.files?.[0] ?? null;
  }

  async sendFile() {
    if (!this.file || !this.threadId || this.sending) return;
    this.sending = true;
    try {
      const r = await this.api.sendChatFile(this.threadId, this.file);
      if (r.ok) {
        this.file = null;
        const inp = document.getElementById('chatFile') as HTMLInputElement | null;
        if (inp) inp.value = '';
        this.mergeNewMessages([r.data]);
        setTimeout(() => this.scrollToBottom(), 0);
        this.throttledMarkRead();
      } else {
        alert(r.error.message || 'تعذّر رفع الملف');
      }
    } catch (err: any) {
      alert(err?.error?.error?.message || 'تعذّر رفع الملف');
    } finally {
      this.sending = false;
    }
  }

  onScroll(e: Event) {
    const el = e.target as HTMLElement;
    if (el.scrollTop < 30) this.loadOlder();
  }

  private scrollToBottom() {
    const box = document.getElementById('msgBox');
    if (box) box.scrollTop = box.scrollHeight;
  }

  private mergeNewMessages(newOnes: ChatMessage[]) {
    if (!newOnes?.length) return;
    const seen = new Map(this.messages.map(m => [m.id, true]));
    let changed = false;
    for (const m of newOnes) {
      if (!seen.has(m.id)) {
        this.messages.push(m);
        changed = true;
      }
    }
    if (changed) this.messages.sort((a, b) => a.id - b.id);
  }

  private throttledMarkRead() {
    if (this.markReadTimer) clearTimeout(this.markReadTimer);
    this.markReadTimer = setTimeout(() => this.markReadIfVisible(), 300);
  }

  private async markReadIfVisible() {
    if (!this.threadId || !this.messages.length) return;
    const lastId = this.messages[this.messages.length - 1].id;
    try { await this.api.markChatRead(this.threadId, lastId); } catch { }
  }

  isMine(m: ChatMessage) {
    return this.myId != null && m.sender_id === this.myId;
  }

  trackById(_: number, m: ChatMessage) { return m.id; }

  /** ====== הפונקציה לפתיחת קבצים בחלון חדש ====== */
 openFile(fileUrl: string | null | undefined) {
  if (!fileUrl) return;
  window.open(fileUrl, '_blank', 'noopener');
}

}