import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute } from '@angular/router';
import { ApiService, AdminEmployeeProfile } from '../../../core/api.service';

@Component({
  selector: 'app-employee-profile',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './employee-profile.component.html',
  styleUrls: ['./employee-profile.component.scss']
})
export class EmployeeProfileComponent implements OnInit {
  // Read the employee id from the route.
  private route = inject(ActivatedRoute);

  // Use the shared API service to load the profile.
  private api = inject(ApiService);

  employeeId: number | null = null;
  loading = false;

  // Keep a safe default object so the page can render before data arrives.
  profile: AdminEmployeeProfile = {
    id: 0,
    name: '',
    email: '',
    status: '',
    role_slug: '',
    role_name: '',
    experience: '',
    bio: '',
    skills: '',
    notes: '',
    department: '',
    phone: '',
    address: '',
    cv: '',
    previous_jobs: ''
  };

  ngOnInit(): void {
    // Read the employee id from /admin/employee-profile/:id
    this.employeeId = Number(this.route.snapshot.paramMap.get('id')) || null;

    if (!this.employeeId) return;

    this.loadProfile(this.employeeId);
  }

  // Selected CV file from the file input.
cvFile: File | null = null;

// Upload state for the CV file.
uploadingCv = false;

// Handle file input change event.
onCvPicked(ev: Event) {
  const input = ev.target as HTMLInputElement;
  this.cvFile = input.files?.[0] ?? null;
}

// Upload the selected CV to the backend and refresh profile.
async uploadCv() {
  if (!this.employeeId || !this.cvFile || this.uploadingCv) return;

  this.uploadingCv = true;

  try {
    const r = await this.api.adminUploadEmployeeCv(this.employeeId, this.cvFile);

    if (r.ok) {
      // Update UI immediately (cv is stored in employee_profiles by backend).
      this.profile = { ...this.profile, cv: r.data.file_url };

      // Clear file input selection.
      this.cvFile = null;
      const inp = document.getElementById('cvFile') as HTMLInputElement | null;
      if (inp) inp.value = '';
    } else {
      alert(r.error.message || 'Failed to upload CV');
    }
  } catch (e: any) {
    alert(e?.error?.error?.message || 'Failed to upload CV');
  } finally {
    this.uploadingCv = false;
  }
}

  private loadProfile(employeeId: number): void {
    // Show loading state while the profile is being fetched.
    this.loading = true;

    this.api.adminGetEmployeeProfile(employeeId).subscribe({
      next: (res) => {
        if (res.ok) {
          this.profile = res.data;
        }
        this.loading = false;
      },
      error: () => {
        this.loading = false;
      }
    });
  }
}