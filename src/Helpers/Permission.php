<?php

namespace Src\Helpers;

use Src\Middleware\AuthMiddleware;

class Permission
{
    // Rol tanımları
    const ROLE_INSPECTOR = 'inspector';           // Kontrolör (saha turu yapan)
    const ROLE_ACTION_OWNER = 'action_owner';     // Aksiyon Sahibi
    const ROLE_DEPARTMENT_HEAD = 'department_head'; // Departman Sorumlusu
    const ROLE_HSE = 'hse';                       // HSE Uzmanı
    const ROLE_UPPER_MANAGEMENT = 'upper_management'; // Üst Yönetim
    const ROLE_ADMIN = 'admin';                   // Admin (tam yetkili)

    // Yetki tanımları
    const PERM_CREATE_CHECKLIST = 'create_checklist';
    const PERM_UPDATE_CHECKLIST = 'update_checklist';
    const PERM_DELETE_CHECKLIST = 'delete_checklist';
    const PERM_VIEW_CHECKLIST = 'view_checklist';
    
    const PERM_START_FIELD_TOUR = 'start_field_tour';
    const PERM_COMPLETE_FIELD_TOUR = 'complete_field_tour';
    
    const PERM_CREATE_ACTION = 'create_action';
    const PERM_ASSIGN_ACTION = 'assign_action';
    const PERM_UPDATE_ACTION = 'update_action';
    const PERM_CHANGE_DUE_DATE = 'change_due_date';
    const PERM_SET_RISK_SCORE = 'set_risk_score';
    const PERM_COMPLETE_ACTION = 'complete_action';
    
    const PERM_REQUEST_CLOSURE = 'request_closure';
    const PERM_APPROVE_CLOSURE = 'approve_closure';
    const PERM_REJECT_CLOSURE = 'reject_closure';
    const PERM_UPPER_APPROVE_CLOSURE = 'upper_approve_closure';
    
    const PERM_VIEW_DASHBOARD = 'view_dashboard';
    const PERM_VIEW_REPORTS = 'view_reports';
    const PERM_EXPORT_DATA = 'export_data';
    
    const PERM_MANAGE_USERS = 'manage_users';
    const PERM_MANAGE_PERMISSIONS = 'manage_permissions';

    /**
     * Rol bazlı yetki matrisi
     */
    private static function getRolePermissions(): array
    {
        return [
            self::ROLE_ADMIN => [
                // Admin tüm yetkilere sahip
                self::PERM_CREATE_CHECKLIST,
                self::PERM_UPDATE_CHECKLIST,
                self::PERM_DELETE_CHECKLIST,
                self::PERM_VIEW_CHECKLIST,
                self::PERM_START_FIELD_TOUR,
                self::PERM_COMPLETE_FIELD_TOUR,
                self::PERM_CREATE_ACTION,
                self::PERM_ASSIGN_ACTION,
                self::PERM_UPDATE_ACTION,
                self::PERM_CHANGE_DUE_DATE,
                self::PERM_SET_RISK_SCORE,
                self::PERM_COMPLETE_ACTION,
                self::PERM_REQUEST_CLOSURE,
                self::PERM_APPROVE_CLOSURE,
                self::PERM_REJECT_CLOSURE,
                self::PERM_UPPER_APPROVE_CLOSURE,
                self::PERM_VIEW_DASHBOARD,
                self::PERM_VIEW_REPORTS,
                self::PERM_EXPORT_DATA,
                self::PERM_MANAGE_USERS,
                self::PERM_MANAGE_PERMISSIONS,
            ],
            
            self::ROLE_HSE => [
                // HSE Uzmanı - Checklist ve risk yönetimi
                self::PERM_CREATE_CHECKLIST,
                self::PERM_UPDATE_CHECKLIST,
                self::PERM_VIEW_CHECKLIST,
                self::PERM_START_FIELD_TOUR,
                self::PERM_COMPLETE_FIELD_TOUR,
                self::PERM_CREATE_ACTION,
                self::PERM_ASSIGN_ACTION,
                self::PERM_UPDATE_ACTION,
                self::PERM_CHANGE_DUE_DATE,
                self::PERM_SET_RISK_SCORE,
                self::PERM_APPROVE_CLOSURE,
                self::PERM_REJECT_CLOSURE,
                self::PERM_VIEW_DASHBOARD,
                self::PERM_VIEW_REPORTS,
                self::PERM_EXPORT_DATA,
            ],
            
            self::ROLE_UPPER_MANAGEMENT => [
                // Üst Yönetim - Onay ve raporlama
                self::PERM_VIEW_CHECKLIST,
                self::PERM_VIEW_DASHBOARD,
                self::PERM_VIEW_REPORTS,
                self::PERM_EXPORT_DATA,
                self::PERM_UPPER_APPROVE_CLOSURE,
                self::PERM_CHANGE_DUE_DATE,
            ],
            
            self::ROLE_DEPARTMENT_HEAD => [
                // Departman Sorumlusu - Kendi departmanı için
                self::PERM_VIEW_CHECKLIST,
                self::PERM_ASSIGN_ACTION,
                self::PERM_UPDATE_ACTION,
                self::PERM_CHANGE_DUE_DATE,
                self::PERM_APPROVE_CLOSURE,
                self::PERM_REJECT_CLOSURE,
                self::PERM_VIEW_DASHBOARD,
                self::PERM_VIEW_REPORTS,
            ],
            
            self::ROLE_INSPECTOR => [
                // Kontrolör - Saha turu ve gözlem
                self::PERM_VIEW_CHECKLIST,
                self::PERM_START_FIELD_TOUR,
                self::PERM_COMPLETE_FIELD_TOUR,
                self::PERM_CREATE_ACTION,
                self::PERM_SET_RISK_SCORE,
                self::PERM_VIEW_DASHBOARD,
            ],
            
            self::ROLE_ACTION_OWNER => [
                // Aksiyon Sahibi - Kendi aksiyonları için
                self::PERM_VIEW_CHECKLIST,
                self::PERM_UPDATE_ACTION,
                self::PERM_REQUEST_CLOSURE,
                self::PERM_VIEW_DASHBOARD,
            ],
        ];
    }

    /**
     * Kullanıcının yetkisi var mı kontrol et
     */
    public static function check(string $permission): bool
    {
        $role = AuthMiddleware::getUserRole();
        $userPermissions = AuthMiddleware::getUserPermissions();

        // Admin her zaman yetkili
        if ($role === self::ROLE_ADMIN) {
            return true;
        }

        // Kullanıcıya özel yetkiler varsa kontrol et
        if (in_array($permission, $userPermissions)) {
            return true;
        }

        // Rol bazlı yetkiler
        $rolePermissions = self::getRolePermissions();
        if (isset($rolePermissions[$role]) && in_array($permission, $rolePermissions[$role])) {
            return true;
        }

        return false;
    }

    /**
     * Birden fazla yetkiden en az birini kontrol et (OR)
     */
    public static function checkAny(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (self::check($permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Tüm yetkileri kontrol et (AND)
     */
    public static function checkAll(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!self::check($permission)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Yetki yoksa hata döndür
     */
    public static function require(string $permission): void
    {
        if (!self::check($permission)) {
            Response::error('Bu işlem için yetkiniz bulunmamaktadır', 403);
            exit;
        }
    }

    /**
     * Birden fazla yetkiden en az birini zorunlu tut
     */
    public static function requireAny(array $permissions): void
    {
        if (!self::checkAny($permissions)) {
            Response::error('Bu işlem için yetkiniz bulunmamaktadır', 403);
            exit;
        }
    }

    /**
     * Kullanıcının rolünü kontrol et
     */
    public static function hasRole(string $role): bool
    {
        return AuthMiddleware::getUserRole() === $role;
    }

    /**
     * Kullanıcının rollerinden birini kontrol et
     */
    public static function hasAnyRole(array $roles): bool
    {
        $userRole = AuthMiddleware::getUserRole();
        return in_array($userRole, $roles);
    }

    /**
     * Kullanıcının tüm yetkilerini getir
     */
    public static function getUserPermissions(): array
    {
        $role = AuthMiddleware::getUserRole();
        $rolePermissions = self::getRolePermissions();
        $userPermissions = AuthMiddleware::getUserPermissions();

        // Rol bazlı yetkiler + kullanıcıya özel yetkiler
        $allPermissions = $rolePermissions[$role] ?? [];
        return array_unique(array_merge($allPermissions, $userPermissions));
    }

    /**
     * Rol açıklamaları
     */
    public static function getRoleDescriptions(): array
    {
        return [
            self::ROLE_ADMIN => [
                'name' => 'Admin',
                'description' => 'Tam yetkili sistem yöneticisi',
                'permissions' => 'Tüm yetkiler',
            ],
            self::ROLE_HSE => [
                'name' => 'HSE Uzmanı',
                'description' => 'İş sağlığı ve güvenliği uzmanı',
                'permissions' => 'Checklist, risk değerlendirme, aksiyon yönetimi',
            ],
            self::ROLE_UPPER_MANAGEMENT => [
                'name' => 'Üst Yönetim',
                'description' => 'Üst düzey yönetici',
                'permissions' => 'Raporlama, üst onay, termin değiştirme',
            ],
            self::ROLE_DEPARTMENT_HEAD => [
                'name' => 'Departman Sorumlusu',
                'description' => 'Departman yöneticisi',
                'permissions' => 'Aksiyon atama, onaylama, termin değiştirme',
            ],
            self::ROLE_INSPECTOR => [
                'name' => 'Kontrolör',
                'description' => 'Saha turu yapan personel',
                'permissions' => 'Saha turu, gözlem, risk puanlama',
            ],
            self::ROLE_ACTION_OWNER => [
                'name' => 'Aksiyon Sahibi',
                'description' => 'Aksiyondan sorumlu personel',
                'permissions' => 'Kendi aksiyonlarını güncelleme, kapatma talebi',
            ],
        ];
    }
}
