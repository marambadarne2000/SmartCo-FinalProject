import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { MatButtonModule } from '@angular/material/button';
import { ApiService } from '../../../core/api.service';

type CrewStatus = 'active' | 'inactive' | 'banned';

type CrewRow = {
  id: number;
  name: string;
  email: string;
  role: string;
  status: CrewStatus | string;
  current_start: string | null;
  current_duration: string | null;
};

@Component({
  selector: 'app-company-crew',
  standalone: true,
  imports: [CommonModule, RouterModule, MatButtonModule],
  templateUrl: './company-crew.component.html',
  styleUrls: ['./company-crew.component.scss']
})
export class CompanyCrewComponent implements OnInit {
  // Shared API service used for loading and updating crew data.
  private api = inject(ApiService);

  rows: CrewRow[] = [];
  loading = false;

  // Tracks row ids currently being saved (prevents double updates).
  saving = new Set<number>();

  ngOnInit(): void {
    this.loadCrew();
  }

  private loadCrew(): void {
    // Show loading state while fetching employees from backend.
    this.loading = true;

    this.api.adminListEmployees({ status: 'all' }).subscribe({
      next: (res) => {
        if (res.ok) {
          this.rows = (res.data || []).map((row: any) => ({
            id: Number(row.id),
            name: row.name || '',
            email: row.email || '',
            role: row.role_name || row.role_slug || '',
            status: this.normalizeStatus(row.status),
            current_start: null,
            current_duration: null
          }));
        } else {
          this.rows = [];
        }
        

        this.loading = false;
      },
      error: () => {
        this.rows = [];
        this.loading = false;
      }
    });
  }

  // Handles status dropdown change and saves it to backend.
  async onStatusChange(row: CrewRow, nextStatusRaw: string): Promise<void> {
    const nextStatus = this.normalizeStatus(nextStatusRaw);
    if (!nextStatus) return;

    // Skip unnecessary save if value did not change.
    if (row.status === nextStatus) return;

    const prevStatus = row.status;

    // Optimistic UI update.
    row.status = nextStatus;
    this.saving.add(row.id);

    try {
      const res = await this.api.adminUpdateEmployeeStatus(row.id, nextStatus);
      if (!res.ok) {
        // Revert status if backend rejects update.
        row.status = prevStatus;
        alert(res.error.message || 'Failed to update status');
      }
    } catch (e: any) {
      // Revert status on network/server errors.
      row.status = prevStatus;
      alert(e?.error?.error?.message || 'Failed to update status');
    } finally {
      this.saving.delete(row.id);
    }
  }

  // Normalizes and validates allowed statuses.
  private normalizeStatus(value: any): CrewStatus {
    const s = String(value || '').toLowerCase().trim();
    if (s === 'active' || s === 'inactive' || s === 'banned') return s;
    return 'active';
  }
}