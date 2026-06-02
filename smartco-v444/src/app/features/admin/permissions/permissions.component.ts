// src/app/features/admin/permissions/permissions.component.ts
import { Component, OnInit, signal, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ApiService, RoleRow, PermRow, ApiResp } from '../../../core/api.service';
import { MatCardModule } from '@angular/material/card';
import { MatTableModule } from '@angular/material/table';
import { MatCheckboxChange, MatCheckboxModule } from '@angular/material/checkbox';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';

type Matrix = Record<number /*roleId*/, Set<number> /*permIds*/>;

@Component({
  selector: 'app-admin-permissions',
  standalone: true,
  imports: [
    CommonModule,
    MatCardModule,
    MatTableModule,
    MatCheckboxModule,
    MatButtonModule,
    MatIconModule
  ],
  templateUrl: './permissions.component.html',
  styleUrls: ['./permissions.component.scss']
})
export class PermissionsComponent implements OnInit {
  private api = inject(ApiService);

  loading = signal(true);
  error = signal<string | null>(null);

  roles = signal<RoleRow[]>([]);
  perms = signal<PermRow[]>([]);

  matrix: Matrix = {};

  displayedColumns: string[] = [];

  ngOnInit(): void {
    this.load();
  }

  private load() {
    this.loading.set(true);
    this.error.set(null);

    this.api.adminGetRolesWithPerms().subscribe({
      next: (r: ApiResp<{ roles: RoleRow[]; permissions: PermRow[] }>) => {
        if (!r.ok) {
          this.error.set(r.error.message || 'Failed to load');
          this.loading.set(false);
          return;
        }

        const roles = r.data.roles;
        const perms = r.data.permissions;

        this.roles.set(roles);
        this.perms.set(perms);

        // Columns: action + a column for each role
        this.displayedColumns = ['action', ...roles.map(ro => 'role_' + ro.id)];

        // Build the editable array
        this.matrix = {};
        for (const ro of roles) {
          this.matrix[ro.id] = new Set<number>(ro.permissions || []);
        }

        this.loading.set(false);
      },
      error: () => {
        this.error.set('Network error');
        this.loading.set(false);
      }
    });
  }

  /** Group permissions by module */
  groupedPerms() {
    const grouped: { module: string; actions: PermRow[] }[] = [];
    const map = new Map<string, PermRow[]>();

    for (const p of this.perms()) {
      if (!map.has(p.module)) {
        map.set(p.module, []);
      }
      map.get(p.module)!.push(p);
    }

    for (const [module, actions] of map.entries()) {
      grouped.push({ module, actions });
    }

    return grouped;
  }

  has(roleId: number, permId: number): boolean {
    return this.matrix[roleId]?.has(permId) ?? false;
  }

  toggle(roleId: number, permId: number, ev: MatCheckboxChange) {
    const set = this.matrix[roleId] ?? (this.matrix[roleId] = new Set<number>());
    if (ev.checked) set.add(permId);
    else set.delete(permId);
  }

  async saveRole(roleId: number) {// In a real app, you would want to handle errors from the API calls and possibly show error messages in the UI instead of using alert()
    const list = Array.from(this.matrix[roleId] ?? []);
    try {
      const resp = await this.api.adminUpdateRolePerms(roleId, list);
      if (!resp.ok) {
        alert(resp.error.message || 'Save failed');
        return;
      }
      alert('Saved');
    } catch {
      alert('Network error');
    }
  }

  async saveAll() { // In a real app, you would want to handle errors from the API calls and possibly show error messages in the UI instead of using alert()
    for (const role of this.roles()) {
      await this.saveRole(role.id);
    }
  }
}