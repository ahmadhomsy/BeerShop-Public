import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend, Counter, Rate } from 'k6/metrics';

// Custom metrics
const productListDuration = new Trend('product_list_duration');
const productFilterDuration = new Trend('product_filter_duration');
const orderListDuration = new Trend('order_list_duration');
const successfulRequests = new Counter('successful_requests');
const failedRequests = new Counter('failed_requests');
const errorRate = new Rate('custom_error_rate');

const BASE_URL = __ENV.BASE_URL || 'http://webserver';
const TEST_EMAIL = __ENV.TEST_EMAIL || 'benchmark_customer@example.com';
const TEST_PASSWORD = __ENV.TEST_PASSWORD || 'password123';

export const options = {
    stages: [
        { duration: '5s', target: 10 },   // Ramp-up to 10 VUs
        { duration: '20s', target: 10 },  // Steady-state load
        { duration: '5s', target: 0 },    // Ramp-down
    ],
    thresholds: {
        http_req_failed: ['rate<0.05'], // Error rate under 5%
    },
};

export function setup() {
    // Authenticate test customer to obtain JWT token for authenticated endpoints
    const loginPayload = JSON.stringify({
        email: TEST_EMAIL,
        password: TEST_PASSWORD,
    });

    const loginRes = http.post(`${BASE_URL}/api/v1/auth/login`, loginPayload, {
        headers: { 'Content-Type': 'application/json' },
    });

    check(loginRes, {
        'login successful': (r) => r.status === 200,
    });

    let token = '';
    try {
        const body = JSON.parse(loginRes.body);
        token = body.data.token;
    } catch (e) {
        console.error('Failed to parse auth token:', e);
    }

    return { token: token };
}

export default function (data) {
    const authHeaders = {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${data.token}`,
    };

    // 1. Benchmark: Public Product Listing
    {
        const res = http.get(`${BASE_URL}/api/v1/products`);
        productListDuration.add(res.timings.duration);
        const passed = check(res, {
            'product listing 200 OK': (r) => r.status === 200,
            'product listing has items': (r) => {
                try {
                    const json = JSON.parse(r.body);
                    return json.data && json.data.items && json.data.items.length > 0;
                } catch {
                    return false;
                }
            },
        });
        if (passed) {
            successfulRequests.add(1);
            errorRate.add(0);
        } else {
            failedRequests.add(1);
            errorRate.add(1);
        }
    }

    sleep(0.05);

    // 2. Benchmark: Product Filtering & Pagination
    {
        const page = Math.floor(Math.random() * 2) + 1;
        const res = http.get(`${BASE_URL}/api/v1/products?category_id=1&second_category_id=1&page=${page}`);
        productFilterDuration.add(res.timings.duration);
        const passed = check(res, {
            'product filter 200 OK': (r) => r.status === 200,
        });
        if (passed) {
            successfulRequests.add(1);
            errorRate.add(0);
        } else {
            failedRequests.add(1);
            errorRate.add(1);
        }
    }

    sleep(0.05);

    // 3. Benchmark: Authenticated Customer Order Listing (exercising 200,000+ orders table)
    {
        const orderPage = Math.floor(Math.random() * 5) + 1;
        const res = http.get(`${BASE_URL}/api/v1/orders?page=${orderPage}&per_page=10`, {
            headers: authHeaders,
        });
        orderListDuration.add(res.timings.duration);
        const passed = check(res, {
            'order listing 200 OK': (r) => r.status === 200,
            'order listing has pagination': (r) => {
                try {
                    const json = JSON.parse(r.body);
                    return json.data && json.data.pagination && json.data.pagination.total > 0;
                } catch {
                    return false;
                }
            },
        });
        if (passed) {
            successfulRequests.add(1);
            errorRate.add(0);
        } else {
            failedRequests.add(1);
            errorRate.add(1);
        }
    }

    sleep(0.1);
}
