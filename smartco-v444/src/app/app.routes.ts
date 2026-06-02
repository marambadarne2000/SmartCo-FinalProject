// src/app/app.routes.ts
import { Routes } from '@angular/router';
import { authGuard } from './core/auth.guard';

export const routes: Routes = [
  /* ================= صفحات الضيوف (بدون تسجيل دخول) ================= */
  {
    path: 'auth/login',
    loadComponent: () =>
      import('./features/auth/login/login.component').then(
        m => m.AuthLoginComponent
      ),
  },
  {
    path: 'reset-password',  // נתיב לדף איפוס סיסמה
    loadComponent: () =>
      import('./features/auth/reset-password/reset-password.component').then(
        m => m.ResetPasswordComponent
      ),
    data: { title: 'איפוס סיסמה' },
  },
  

  /* ================= صفحات بعد تسجيل الدخول ================= */
  {

    path: '',
    loadComponent: () =>
      import('./features/layout/shell/shell.component').then(
        m => m.ShellComponent
      ),
    canActivate: [authGuard], // حماية المسار الرئيسي
    children: [
      /* ===== الداشبورد ===== */
      {
        path: 'dashboard',
        loadComponent: () =>
          import('./features/dashboard/dashboard.component').then(
            m => m.DashboardComponent
          ),
        data: { title: 'لوحة التحكم' },
      },

      /* ===== المشاريع ===== */
      {
        path: 'projects/list',
        loadComponent: () =>
          import('./features/projects/list/list.component').then(
            m => m.ProjectsListComponent
          ),
        data: { title: 'المشاريع' },
      },

      /* ===== المهام (قائمة عامة واختيار بحسب المشروع) ===== */
      {
        path: 'tasks',
        loadComponent: () =>
          import('./features/tasks/list/list.component').then(
            m => m.TasksListComponent
          ),
        data: { title: 'المهام' },
      },
      {
        path: 'tasks/:project_id',
        loadComponent: () =>
          import('./features/tasks/list/list.component').then(
            m => m.TasksListComponent
          ),
        data: { title: 'مهام المشروع' },
      },

      /* ===== تفاصيل مهمة محددة + شاشة الشات ===== */
      {
        path: 'tasks/:id/detail',
        loadComponent: () =>
          import('./features/tasks/detail/detail.component').then(
            m => m.TaskDetailComponent
          ),
        canActivate: [authGuard],
        runGuardsAndResolvers: 'pathParamsChange',
        data: { title: 'تفاصيل المهمة' },
      },
      {
        path: 'tasks/:id/chat',
        loadComponent: () =>
          import('./features/task-chat/chat-page.component').then(
            m => m.ChatPageComponent
          ),
        canActivate: [authGuard],
        runGuardsAndResolvers: 'pathParamsChange',
        data: { title: 'محادثة المهمة' },
      },

      /* ===== الإشعارات ===== */
      {
        path: 'notifications',
        loadComponent: () =>
          import('./features/notifications/notifications.component').then(
            m => m.NotificationsComponent
          ),
        data: { title: 'الإشعارات' },
      },

      /* ===== صندوق رسائل المهام (Inbox) — متاح للجميع الآن ===== */
      {
        path: 'chat-inbox',
        loadComponent: () =>
          import('./features/task-chat/inbox/inbox.component').then(
            m => m.ChatInboxComponent
          ),
        canActivate: [authGuard],
        data: { title: 'صندوق رسائل المهام' },
      },

      /* ================= صفحة إضافة مستخدم جديد (admin + manager) ================= */
      {
        path: 'admin/add-user',
        loadComponent: () =>
          import('./features/admin/add-user/add-user.component').then(
            m => m.AddUserComponent
          ),
        canActivate: [authGuard],
        data: { roles: ['admin', 'manager'], title: 'إضافة مستخدم' },
      },

      /* ================= صفحة الرواتب (Payroll) ================= */
      {
        path: 'payroll',
        loadComponent: () =>
          import('./features/payroll/payroll-page.component').then(
            m => m.PayrollPageComponent
          ),
        canActivate: [authGuard],
        data: { roles: ['admin', 'manager'], title: 'الرواتب' },
      },

      /* ================= صفحة إدارة الصلاحيات (لـ admin فقط) ================= */
      {
        path: 'admin/permissions',
        loadComponent: () =>
          import('./features/admin/permissions/permissions.component').then(
            m => m.PermissionsComponent
          ),
        canActivate: [authGuard],
        data: { roles: ['admin'], title: 'صلاحيات النظام' },
      },

      {
  path: 'admin/employees/:id',
  loadComponent: () =>
    import('./features/admin/employee-details/employee-details.component').then(
      m => m.EmployeeDetailsComponent
    ),
  canActivate: [authGuard],
  data: { roles: ['admin'], title: 'Employee Details' },
},


{
  path: 'admin/company-crew',
  loadComponent: () =>
    import('./features/admin/company-crew/company-crew.component').then(
      m => m.CompanyCrewComponent
    ),
  canActivate: [authGuard],
  data: { roles: ['admin'], title: 'העובדים הנוכחיים בחברה' },
},

{
  path: 'admin/employee-profile/:id',
  loadComponent: () =>
    import('./features/admin/employee-profile/employee-profile.component').then(
      m => m.EmployeeProfileComponent
    ),
  canActivate: [authGuard],
  data: { roles: ['admin'], title: 'פרטים אישיים של עובד' },
},
      

      /* ================= إرسال إشعار (admin فقط) ================= */
      {
        path: 'admin/send-notification',
        loadComponent: () =>
          import('./features/admin/send-notification/send-notification.component').then(
            m => m.SendNotificationComponent
          ),
        canActivate: [authGuard],
        data: { roles: ['admin'], title: 'إرسال إشعار' },
      },

      /* ================= صفحة إدارة الموظفين (admin فقط) ================= */
      {
        path: 'admin/employees',
        loadComponent: () =>
          import('./features/admin/admin-employees/admin-employees.component').then(
            m => m.AdminEmployeesComponent
          ),
        canActivate: [authGuard],
        data: { roles: ['admin'], title: 'إدارة الموظفين' },
      },


      /* ================= إعادة توجيه للداشبورد ================= */
      { path: '', redirectTo: 'dashboard', pathMatch: 'full' },
    ],
  },

  

  /* ================= أي مسار غير معروف يرجع للصفحة الرئيسية ================= */
  { path: '**', redirectTo: '' },
];
