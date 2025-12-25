#!/usr/bin/env node

/**
 * HSE API Bildirim Sistemi Test Scripti
 * 
 * 3 farklı kullanıcı rolü ile bildirim sistemini test eder:
 * - Creator (b@aa.com, ID: 2430)
 * - Assignee (a@aa.com, ID: 2399)
 * - Manager (c@aa.com, ID: 2431)
 */

const https = require('https');
const http = require('http');

// Test Konfigürasyonu
const CONFIG = {
    AUTH_API: 'http://central-auth-and-notification-app.apps.misafirus.com',
    HSE_API: 'https://takipus.apps.misafirus.com', // Production API
    USERS: {
        creator: { email: 'b@aa.com', id: 2430, name: 'Aksiyon Açan' },
        assignee: { email: 'a@aa.com', id: 2399, name: 'Aksiyon Atanan' },
        manager: { email: 'c@aa.com', id: 2431, name: 'Üst Yönetici' }
    }
};

// Test sonuçları
const testResults = {
    scenarios: [],
    totalTests: 0,
    passedTests: 0,
    failedTests: 0
};

// Renk kodları
const colors = {
    reset: '\x1b[0m',
    bright: '\x1b[1m',
    green: '\x1b[32m',
    red: '\x1b[31m',
    yellow: '\x1b[33m',
    blue: '\x1b[34m',
    cyan: '\x1b[36m'
};

// HTTP Request Helper
function makeRequest(url, options = {}) {
    return new Promise((resolve, reject) => {
        const urlObj = new URL(url);
        const protocol = urlObj.protocol === 'https:' ? https : http;

        const requestOptions = {
            hostname: urlObj.hostname,
            port: urlObj.port,
            path: urlObj.pathname + urlObj.search,
            method: options.method || 'GET',
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            }
        };

        const req = protocol.request(requestOptions, (res) => {
            let data = '';
            res.on('data', (chunk) => data += chunk);
            res.on('end', () => {
                try {
                    const parsed = JSON.parse(data);
                    resolve({ status: res.statusCode, data: parsed });
                } catch (e) {
                    resolve({ status: res.statusCode, data: data });
                }
            });
        });

        req.on('error', reject);

        if (options.body) {
            req.write(JSON.stringify(options.body));
        }

        req.end();
    });
}

// Login fonksiyonu
async function login(email) {
    console.log(`${colors.cyan}🔐 Login: ${email}${colors.reset}`);

    try {
        const response = await makeRequest(`${CONFIG.AUTH_API}/auth/login`, {
            method: 'POST',
            body: {
                username: email,
                password: 'test123' // Test ortamında password kontrolü yok
            }
        });

        if (response.status === 200 && response.data.success) {
            const { token, data } = response.data;
            const user = data.user;
            console.log(`${colors.green}✓ Login başarılı - User ID: ${user.id}, Company: ${user.username}${colors.reset}`);
            return { token, user };
        } else {
            throw new Error(`Login başarısız: ${JSON.stringify(response.data)}`);
        }
    } catch (error) {
        console.error(`${colors.red}✗ Login hatası: ${error.message}${colors.reset}`);
        throw error;
    }
}

// Bildirim kontrolü
async function getNotifications(userId) {
    try {
        const response = await makeRequest(`${CONFIG.HSE_API}/api/v1/notifications/user/${userId}`);

        if (response.status === 200 && response.data.success) {
            return response.data.data;
        }
        return [];
    } catch (error) {
        console.error(`${colors.red}✗ Bildirim alma hatası: ${error.message}${colors.reset}`);
        return [];
    }
}

// Aksiyon oluşturma
async function createAction(creatorId, assigneeId, upperApproverId = null) {
    console.log(`\n${colors.blue}📝 Aksiyon oluşturuluyor...${colors.reset}`);

    const actionData = {
        company_id: 'F9946',
        title: `Test Aksiyon - ${new Date().toISOString()}`,
        description: 'Bu bir test aksiyonudur',
        location: 'Test Lokasyonu',
        assigned_to_user_id: assigneeId,
        created_by: creatorId,
        due_date: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
        risk_probability: 3,
        risk_severity: 3,
        source_type: 'manual'
    };

    if (upperApproverId) {
        actionData.upper_approver_id = upperApproverId;
    }

    try {
        const response = await makeRequest(`${CONFIG.HSE_API}/api/v1/actions/manual`, {
            method: 'POST',
            body: actionData
        });

        if (response.status === 201 && response.data.success) {
            const action = response.data.data;
            console.log(`${colors.green}✓ Aksiyon oluşturuldu - ID: ${action.id}${colors.reset}`);
            return action;
        } else {
            throw new Error(`Aksiyon oluşturulamadı: ${JSON.stringify(response.data)}`);
        }
    } catch (error) {
        console.error(`${colors.red}✗ Aksiyon oluşturma hatası: ${error.message}${colors.reset}`);
        throw error;
    }
}

// Kapatma talebi gönderme
async function requestClosure(actionId, requestedBy) {
    console.log(`\n${colors.blue}🔒 Kapatma talebi gönderiliyor...${colors.reset}`);

    try {
        const response = await makeRequest(`${CONFIG.HSE_API}/api/v1/actions/${actionId}/closure-request`, {
            method: 'POST',
            body: {
                requested_by: requestedBy,
                closure_description: 'Test kapatma açıklaması - Aksiyon tamamlandı',
                evidence_files: []
            }
        });

        if (response.status === 201 && response.data.success) {
            const closure = response.data.data;
            console.log(`${colors.green}✓ Kapatma talebi gönderildi - Closure ID: ${closure.id}${colors.reset}`);
            return closure;
        } else {
            throw new Error(`Kapatma talebi gönderilemedi: ${JSON.stringify(response.data)}`);
        }
    } catch (error) {
        console.error(`${colors.red}✗ Kapatma talebi hatası: ${error.message}${colors.reset}`);
        throw error;
    }
}

// Kapatma talebini onaylama
async function approveClosure(actionId, closureId, reviewedBy) {
    console.log(`\n${colors.blue}✅ Kapatma talebi onaylanıyor...${colors.reset}`);

    try {
        const response = await makeRequest(`${CONFIG.HSE_API}/api/v1/actions/${actionId}/closure/${closureId}/approve`, {
            method: 'PUT',
            body: {
                reviewed_by: reviewedBy,
                review_notes: 'Test onay notu'
            }
        });

        if (response.status === 200 && response.data.success) {
            console.log(`${colors.green}✓ Kapatma talebi onaylandı${colors.reset}`);
            return response.data.data;
        } else {
            throw new Error(`Kapatma talebi onaylanamadı: ${JSON.stringify(response.data)}`);
        }
    } catch (error) {
        console.error(`${colors.red}✗ Onaylama hatası: ${error.message}${colors.reset}`);
        throw error;
    }
}

// Kapatma talebini reddetme
async function rejectClosure(actionId, closureId, reviewedBy) {
    console.log(`\n${colors.blue}❌ Kapatma talebi reddediliyor...${colors.reset}`);

    try {
        const response = await makeRequest(`${CONFIG.HSE_API}/api/v1/actions/${actionId}/closure/${closureId}/reject`, {
            method: 'PUT',
            body: {
                reviewed_by: reviewedBy,
                review_notes: 'Test red notu - Yeterli değil'
            }
        });

        if (response.status === 200 && response.data.success) {
            console.log(`${colors.green}✓ Kapatma talebi reddedildi${colors.reset}`);
            return response.data.data;
        } else {
            throw new Error(`Kapatma talebi reddedilemedi: ${JSON.stringify(response.data)}`);
        }
    } catch (error) {
        console.error(`${colors.red}✗ Reddetme hatası: ${error.message}${colors.reset}`);
        throw error;
    }
}

// Aksiyon detayı
async function getAction(actionId) {
    try {
        const response = await makeRequest(`${CONFIG.HSE_API}/api/v1/actions/${actionId}`);

        if (response.status === 200 && response.data.success) {
            return response.data.data;
        }
        return null;
    } catch (error) {
        console.error(`${colors.red}✗ Aksiyon detayı alma hatası: ${error.message}${colors.reset}`);
        return null;
    }
}

// Test assertion
function assert(condition, message) {
    testResults.totalTests++;
    if (condition) {
        console.log(`${colors.green}  ✓ ${message}${colors.reset}`);
        testResults.passedTests++;
        return true;
    } else {
        console.log(`${colors.red}  ✗ ${message}${colors.reset}`);
        testResults.failedTests++;
        return false;
    }
}

// Bildirim kontrolü
async function checkNotification(userId, expectedType, expectedCount = 1) {
    await new Promise(resolve => setTimeout(resolve, 1000)); // Bildirimlerin oluşması için bekle

    const notifications = await getNotifications(userId);
    const matchingNotifications = notifications.filter(n => n.type === expectedType);

    return assert(
        matchingNotifications.length >= expectedCount,
        `${expectedType} bildirimi kontrolü (Beklenen: ${expectedCount}, Bulunan: ${matchingNotifications.length})`
    );
}

// SENARYO 1: Basit Aksiyon (Üst Yönetici Onayı YOK)
async function scenario1(users) {
    console.log(`\n${colors.bright}${colors.yellow}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${colors.reset}`);
    console.log(`${colors.bright}SENARYO 1: Basit Aksiyon (Üst Yönetici Onayı YOK)${colors.reset}`);
    console.log(`${colors.yellow}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${colors.reset}\n`);

    const scenarioResult = { name: 'Senaryo 1', steps: [], passed: true };

    try {
        // 1. Creator aksiyon oluşturur
        const action = await createAction(users.creator.id, users.assignee.id);
        scenarioResult.steps.push('Aksiyon oluşturuldu');

        // 2. Assignee'ye bildirim kontrolü
        await checkNotification(users.assignee.id, 'action_assigned');
        scenarioResult.steps.push('Assignee bildirimi kontrol edildi');

        // 3. Assignee kapatma talebi gönderir
        const closure = await requestClosure(action.id, users.assignee.id);
        scenarioResult.steps.push('Kapatma talebi gönderildi');

        // 4. Creator'a bildirim kontrolü
        await checkNotification(users.creator.id, 'closure_requested');
        scenarioResult.steps.push('Creator bildirimi kontrol edildi');

        // 5. Creator onaylar
        await approveClosure(action.id, closure.id, users.creator.id);
        scenarioResult.steps.push('Kapatma talebi onaylandı');

        // 6. Aksiyon durumu kontrolü
        const updatedAction = await getAction(action.id);
        assert(updatedAction.status === 'completed', 'Aksiyon durumu "completed" olmalı');
        scenarioResult.steps.push('Aksiyon durumu kontrol edildi');

        // 7. Assignee'ye tamamlanma bildirimi
        await checkNotification(users.assignee.id, 'action_completed');
        scenarioResult.steps.push('Tamamlanma bildirimi kontrol edildi');

        console.log(`\n${colors.green}${colors.bright}✓ SENARYO 1 BAŞARILI${colors.reset}\n`);
    } catch (error) {
        console.error(`\n${colors.red}${colors.bright}✗ SENARYO 1 BAŞARISIZ: ${error.message}${colors.reset}\n`);
        scenarioResult.passed = false;
    }

    testResults.scenarios.push(scenarioResult);
}

// SENARYO 2: Üst Yönetici Onaylı Aksiyon
async function scenario2(users) {
    console.log(`\n${colors.bright}${colors.yellow}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${colors.reset}`);
    console.log(`${colors.bright}SENARYO 2: Üst Yönetici Onaylı Aksiyon${colors.reset}`);
    console.log(`${colors.yellow}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${colors.reset}\n`);

    const scenarioResult = { name: 'Senaryo 2', steps: [], passed: true };

    try {
        // 1. Creator aksiyon oluşturur (Manager'ı üst yönetici olarak seçer)
        const action = await createAction(users.creator.id, users.assignee.id, users.manager.id);
        scenarioResult.steps.push('Aksiyon oluşturuldu (üst yönetici ile)');

        // 2. Assignee'ye bildirim kontrolü
        await checkNotification(users.assignee.id, 'action_assigned');
        scenarioResult.steps.push('Assignee bildirimi kontrol edildi');

        // 2b. YENİ: Manager'a aksiyon oluşturma bildirimi kontrolü
        await checkNotification(users.manager.id, 'action_created');
        scenarioResult.steps.push('Manager\'a aksiyon oluşturma bildirimi kontrol edildi');

        // 3. Assignee kapatma talebi gönderir
        const closure = await requestClosure(action.id, users.assignee.id);
        scenarioResult.steps.push('Kapatma talebi gönderildi');

        // 4. Creator'a bildirim kontrolü
        await checkNotification(users.creator.id, 'closure_requested');
        scenarioResult.steps.push('Creator bildirimi kontrol edildi');

        // 5. Creator ilk onayı verir
        await approveClosure(action.id, closure.id, users.creator.id);
        scenarioResult.steps.push('İlk onay verildi');

        // 6. Manager'a bildirim kontrolü
        await checkNotification(users.manager.id, 'upper_approval_required');
        scenarioResult.steps.push('Manager bildirimi kontrol edildi');

        // 7. Manager ikinci onayı verir
        await approveClosure(action.id, closure.id, users.manager.id);
        scenarioResult.steps.push('İkinci onay verildi');

        // 8. Aksiyon durumu kontrolü
        const updatedAction = await getAction(action.id);
        assert(updatedAction.status === 'completed', 'Aksiyon durumu "completed" olmalı');
        scenarioResult.steps.push('Aksiyon durumu kontrol edildi');

        // 9. Tamamlanma bildirimleri
        await checkNotification(users.assignee.id, 'action_completed');
        scenarioResult.steps.push('Assignee tamamlanma bildirimi kontrol edildi');

        console.log(`\n${colors.green}${colors.bright}✓ SENARYO 2 BAŞARILI${colors.reset}\n`);
    } catch (error) {
        console.error(`\n${colors.red}${colors.bright}✗ SENARYO 2 BAŞARISIZ: ${error.message}${colors.reset}\n`);
        scenarioResult.passed = false;
    }

    testResults.scenarios.push(scenarioResult);
}

// SENARYO 3: Red Senaryosu (Üst Yönetici ile)
async function scenario3(users) {
    console.log(`\n${colors.bright}${colors.yellow}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${colors.reset}`);
    console.log(`${colors.bright}SENARYO 3: Red Senaryosu (Üst Yönetici ile)${colors.reset}`);
    console.log(`${colors.yellow}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${colors.reset}\n`);

    const scenarioResult = { name: 'Senaryo 3', steps: [], passed: true };

    try {
        // 1. Creator aksiyon oluşturur (Manager ile)
        const action = await createAction(users.creator.id, users.assignee.id, users.manager.id);
        scenarioResult.steps.push('Aksiyon oluşturuldu (üst yönetici ile)');

        // 2. Assignee'ye bildirim kontrolü
        await checkNotification(users.assignee.id, 'action_assigned');
        scenarioResult.steps.push('Assignee bildirimi kontrol edildi');

        // 2b. Manager'a aksiyon oluşturma bildirimi kontrolü
        await checkNotification(users.manager.id, 'action_created');
        scenarioResult.steps.push('Manager\'a aksiyon oluşturma bildirimi kontrol edildi');

        // 3. Assignee kapatma talebi gönderir
        const closure = await requestClosure(action.id, users.assignee.id);
        scenarioResult.steps.push('Kapatma talebi gönderildi');

        // 4. Creator'a bildirim kontrolü
        await checkNotification(users.creator.id, 'closure_requested');
        scenarioResult.steps.push('Creator bildirimi kontrol edildi');

        // 5. Creator reddeder
        await rejectClosure(action.id, closure.id, users.creator.id);
        scenarioResult.steps.push('Kapatma talebi reddedildi');

        // 6. Aksiyon durumu kontrolü
        const updatedAction = await getAction(action.id);
        assert(updatedAction.status === 'open', 'Aksiyon durumu "open" olmalı');
        scenarioResult.steps.push('Aksiyon durumu kontrol edildi');

        // 7. Assignee'ye red bildirimi
        await checkNotification(users.assignee.id, 'closure_rejected');
        scenarioResult.steps.push('Assignee\'ye red bildirimi kontrol edildi');

        // 8. YENİ: Manager'a da red bildirimi
        await checkNotification(users.manager.id, 'closure_rejected');
        scenarioResult.steps.push('Manager\'a red bildirimi kontrol edildi');

        console.log(`\n${colors.green}${colors.bright}✓ SENARYO 3 BAŞARILI${colors.reset}\n`);
    } catch (error) {
        console.error(`\n${colors.red}${colors.bright}✗ SENARYO 3 BAŞARISIZ: ${error.message}${colors.reset}\n`);
        scenarioResult.passed = false;
    }

    testResults.scenarios.push(scenarioResult);
}

// Test sonuçlarını yazdır
function printResults() {
    console.log(`\n${colors.bright}${colors.cyan}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${colors.reset}`);
    console.log(`${colors.bright}TEST SONUÇLARI${colors.reset}`);
    console.log(`${colors.cyan}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${colors.reset}\n`);

    testResults.scenarios.forEach(scenario => {
        const icon = scenario.passed ? '✓' : '✗';
        const color = scenario.passed ? colors.green : colors.red;
        console.log(`${color}${icon} ${scenario.name}${colors.reset}`);
        scenario.steps.forEach(step => {
            console.log(`  - ${step}`);
        });
        console.log();
    });

    console.log(`${colors.bright}Toplam Test: ${testResults.totalTests}${colors.reset}`);
    console.log(`${colors.green}Başarılı: ${testResults.passedTests}${colors.reset}`);
    console.log(`${colors.red}Başarısız: ${testResults.failedTests}${colors.reset}`);

    const successRate = ((testResults.passedTests / testResults.totalTests) * 100).toFixed(2);
    console.log(`\n${colors.bright}Başarı Oranı: ${successRate}%${colors.reset}\n`);
}

// Ana test fonksiyonu
async function main() {
    console.log(`\n${colors.bright}${colors.blue}╔════════════════════════════════════════════════════════╗${colors.reset}`);
    console.log(`${colors.bright}${colors.blue}║     HSE API BİLDİRİM SİSTEMİ TEST SENARYOLARI         ║${colors.reset}`);
    console.log(`${colors.bright}${colors.blue}╚════════════════════════════════════════════════════════╝${colors.reset}\n`);

    try {
        // Login işlemleri
        console.log(`${colors.bright}1. Kullanıcı Login İşlemleri${colors.reset}\n`);

        const creatorAuth = await login(CONFIG.USERS.creator.email);
        await new Promise(resolve => setTimeout(resolve, 1000)); // Bağlantı için bekle

        const assigneeAuth = await login(CONFIG.USERS.assignee.email);
        await new Promise(resolve => setTimeout(resolve, 1000)); // Bağlantı için bekle

        const managerAuth = await login(CONFIG.USERS.manager.email);

        const users = {
            creator: { ...CONFIG.USERS.creator, ...creatorAuth },
            assignee: { ...CONFIG.USERS.assignee, ...assigneeAuth },
            manager: { ...CONFIG.USERS.manager, ...managerAuth }
        };

        console.log(`\n${colors.green}✓ Tüm kullanıcılar başarıyla login oldu${colors.reset}\n`);

        // Test senaryolarını çalıştır
        console.log(`${colors.bright}2. Test Senaryoları${colors.reset}\n`);

        await scenario1(users);
        await scenario2(users);
        await scenario3(users);

        // Sonuçları yazdır
        printResults();

    } catch (error) {
        console.error(`\n${colors.red}${colors.bright}FATAL ERROR: ${error.message}${colors.reset}\n`);
        process.exit(1);
    }
}

// Scripti çalıştır
main();
