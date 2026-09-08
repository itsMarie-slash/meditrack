<?php

declare(strict_types=1);

use MediTrack\Controllers\AuditLogController;
use MediTrack\Controllers\AuthController;
use MediTrack\Controllers\DashboardController;
use MediTrack\Controllers\DistributionController;
use MediTrack\Controllers\DistributionScheduleController;
use MediTrack\Controllers\ForecastController;
use MediTrack\Controllers\MapController;
use MediTrack\Controllers\MedicineController;
use MediTrack\Controllers\PurokController;
use MediTrack\Controllers\ReportController;
use MediTrack\Controllers\SeniorCitizenController;
use MediTrack\Controllers\UserController;

/** @var \MediTrack\Core\Router $router */

const ROLE_BHW = 'bhw';
const ROLE_MIDWIFE = 'midwife';
const ROLE_IPHO = 'ipho';
const ANY_ROLE = [ROLE_BHW, ROLE_MIDWIFE, ROLE_IPHO];
const BHW_MIDWIFE = [ROLE_BHW, ROLE_MIDWIFE];
const BHW_ONLY = [ROLE_BHW];

// --- Auth ---------------------------------------------------------------
$router->add('POST', '/api/auth/login', ['public'], [AuthController::class, 'login']);
$router->add('POST', '/api/auth/logout', ANY_ROLE, [AuthController::class, 'logout']);
$router->add('GET', '/api/auth/me', ANY_ROLE, [AuthController::class, 'me']);

// --- Users (BHW only) ----------------------------------------------------
$router->add('GET', '/api/users', BHW_ONLY, [UserController::class, 'index']);
$router->add('POST', '/api/users', BHW_ONLY, [UserController::class, 'store']);
$router->add('PUT', '/api/users/{id}', BHW_ONLY, [UserController::class, 'update']);
$router->add('DELETE', '/api/users/{id}', BHW_ONLY, [UserController::class, 'destroy']);

// --- Puroks (lookup, used to populate address/filter dropdowns) --------
$router->add('GET', '/api/puroks', ANY_ROLE, [PurokController::class, 'index']);

// --- Senior Citizens -------------------------------------------------------
$router->add('GET', '/api/senior-citizens', BHW_MIDWIFE, [SeniorCitizenController::class, 'index']);
$router->add('GET', '/api/senior-citizens/{id}', BHW_MIDWIFE, [SeniorCitizenController::class, 'show']);
$router->add('POST', '/api/senior-citizens', BHW_ONLY, [SeniorCitizenController::class, 'store']);
$router->add('PUT', '/api/senior-citizens/{id}', BHW_ONLY, [SeniorCitizenController::class, 'update']);
$router->add('DELETE', '/api/senior-citizens/{id}', BHW_ONLY, [SeniorCitizenController::class, 'destroy']);

// --- Medicines --------------------------------------------------------------
$router->add('GET', '/api/medicines', BHW_MIDWIFE, [MedicineController::class, 'index']);
$router->add('GET', '/api/medicines/{id}', BHW_MIDWIFE, [MedicineController::class, 'show']);
$router->add('POST', '/api/medicines', BHW_ONLY, [MedicineController::class, 'store']);
$router->add('PUT', '/api/medicines/{id}', BHW_ONLY, [MedicineController::class, 'update']);
$router->add('DELETE', '/api/medicines/{id}', BHW_ONLY, [MedicineController::class, 'destroy']);
$router->add('POST', '/api/medicines/{id}/stock', BHW_ONLY, [MedicineController::class, 'addStock']);

// --- Distribution Schedules ---------------------------------------------
$router->add('GET', '/api/distribution-schedules', BHW_MIDWIFE, [DistributionScheduleController::class, 'index']);
$router->add('PUT', '/api/distribution-schedules/{id}', BHW_ONLY, [DistributionScheduleController::class, 'update']);

// --- Distributions (transactions) ---------------------------------------
$router->add('GET', '/api/distributions', BHW_MIDWIFE, [DistributionController::class, 'index']);
$router->add('POST', '/api/distributions', BHW_ONLY, [DistributionController::class, 'store']);
$router->add('PUT', '/api/distributions/{id}', BHW_ONLY, [DistributionController::class, 'update']);

// --- GIS Map ----------------------------------------------------------------
$router->add('GET', '/api/map/beneficiaries', BHW_MIDWIFE, [MapController::class, 'beneficiaries']);

// --- Demand Forecasting ------------------------------------------------------
$router->add('GET', '/api/forecasts', ANY_ROLE, [ForecastController::class, 'index']);
$router->add('GET', '/api/forecasts/export', ANY_ROLE, [ForecastController::class, 'export']);

// --- Reports -----------------------------------------------------------------
$router->add('GET', '/api/reports/inventory', BHW_MIDWIFE, [ReportController::class, 'inventory']);
$router->add('GET', '/api/reports/distribution', BHW_MIDWIFE, [ReportController::class, 'distribution']);
$router->add('GET', '/api/reports/{type}/export', ANY_ROLE, [ReportController::class, 'export']);

// --- Dashboard / Audit ---------------------------------------------------
$router->add('GET', '/api/dashboard', ANY_ROLE, [DashboardController::class, 'index']);
$router->add('GET', '/api/audit-logs', BHW_ONLY, [AuditLogController::class, 'index']);
