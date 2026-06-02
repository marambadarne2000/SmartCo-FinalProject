import { Component, OnInit, OnDestroy, inject, signal } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { NgIf } from '@angular/common';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatIconModule } from '@angular/material/icon';
import { MatSidenavModule } from '@angular/material/sidenav';
import { MatListModule } from '@angular/material/list';
import { MatButtonModule } from '@angular/material/button';
import { MatBadgeModule } from '@angular/material/badge';
import { MatTooltipModule } from '@angular/material/tooltip';
import { ApiService, MeData } from '../../../core/api.service';

@Component({
  selector: 'app-shell',
  standalone: true,
  imports: [
    RouterOutlet, RouterLink, RouterLinkActive, NgIf,
    MatToolbarModule, MatIconModule, MatSidenavModule,
    MatListModule, MatButtonModule, MatBadgeModule, MatTooltipModule
  ],
  templateUrl: './shell.component.html',
  styleUrls: ['./shell.component.scss']
})
export class ShellComponent implements OnInit, OnDestroy {
  private api = inject(ApiService);
  private router = inject(Router);

  me = signal<MeData['user'] | null>(null);

  /** System notifications */
  unreadCount = 0;

  /** Task inbox unread count */
  chatUnreadCount = 0;

  /** Timer for periodic updates */
  private tickerId: any = null;

  ngOnInit(): void {
    // Load user data
    this.api.me().subscribe(resp => { if (resp.ok) this.me.set(resp.data.user); });

    // Trigger immediate counts update once on load
    this.tickCounts();

    // Periodic update every 15 seconds
    this.tickerId = setInterval(() => this.tickCounts(), 15000);
  }

  ngOnDestroy(): void {
    if (this.tickerId) {
      clearInterval(this.tickerId);
      this.tickerId = null;
    }
  }

  /** Update counts (notifications + chat) */
  private tickCounts(): void {
    this.loadUnreadNotifications();
    this.loadChatUnread();
  }

  /** Counts unread from /notifications */
  private loadUnreadNotifications(): void {
    this.api.listNotifications(50).subscribe(resp => {
      if (resp.ok) {
        this.unreadCount = resp.data.filter(n => !n.read).length;
      }
    });
  }

  /** Aggregates total unread from chat Inbox */
  private loadChatUnread(): void {
    // Request minimum needed to get total, or you can increase limit
    this.api.getChatInbox({ only_unread: 1, limit: 50, offset: 0 }).subscribe(r => {
      if (r.ok) {
        this.chatUnreadCount = r.data.reduce((sum, row) => sum + (row.unread_count || 0), 0);
      } else {
        this.chatUnreadCount = 0;
      }
    });
  }

  /** Roles */
  private roleSlug(): string {
    const r: any = this.me()?.role;
    return String(typeof r === 'string' ? r : (r?.slug ?? '')).toLowerCase();
  }
  isAdmin(): boolean   { return this.roleSlug() === 'admin'; }
  isManager(): boolean { return this.roleSlug() === 'manager'; }


  /** Logout */
  async logout(): Promise<void> {
    try { await this.api.logout(); } catch {}
    this.router.navigateByUrl('/auth/login');
  }
}