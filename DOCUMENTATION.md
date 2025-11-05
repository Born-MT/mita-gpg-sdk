# Malta GPG SDK - Documentation Index

Complete documentation for the Malta Government Payment Gateway PHP SDK.

## 📚 Quick Links

### Getting Started
- **[README.md](README.md)** - Complete SDK documentation, API reference, and installation guide
- **[QUICKSTART.md](QUICKSTART.md)** - 5-minute quick start guide with basic examples

### Usage & Examples
- **[EXAMPLES.md](EXAMPLES.md)** - 10 real-world usage scenarios with complete code examples
  - E-commerce checkout
  - Hotel reservations with pre-auth
  - Subscription payments
  - Marketplace transactions
  - Government service payments
  - Refund processing
  - Webhook handling
  - Transaction reconciliation
  - Error handling patterns
  - Testing strategies

### Testing
- **[tests/README.md](tests/README.md)** - Complete E2E test suite documentation
  - 63 tests covering all scenarios
  - Mock HTTP client usage
  - Test fixtures
  - CI/CD integration examples

### Reference
- **[CHANGELOG.md](CHANGELOG.md)** - Version history and release notes

## 📖 Documentation Overview

### 1. README.md (Main Documentation)
**What's inside:**
- Installation instructions
- Basic usage examples
- Complete API reference
- Configuration options
- Security best practices
- Error handling guide
- Support information

**When to read:** Start here for complete SDK overview

---

### 2. QUICKSTART.md
**What's inside:**
- 5-minute setup
- First payment creation
- Webhook handling
- Common use cases
- Next steps

**When to read:** When you want to get started quickly

---

### 3. EXAMPLES.md (48KB, 10 scenarios)
**What's inside:**
- Complete working code examples
- Real-world scenarios
- Best practices
- Error handling
- Database integration patterns
- Business logic examples

**Scenarios covered:**
1. E-commerce checkout flow
2. Hotel reservation (pre-auth → capture)
3. Subscription payment handling
4. Marketplace with split payments
5. Government service payments
6. Full & partial refunds
7. Webhook processing with security
8. Daily transaction reconciliation
9. Comprehensive error handling
10. Testing strategies

**When to read:** When implementing specific features

---

### 4. tests/README.md
**What's inside:**
- Complete test suite guide
- 63 E2E tests documentation
- Mock HTTP client usage
- Test fixtures
- CI/CD examples
- Adding new tests

**Test categories:**
- Unit tests (13 tests)
- Payment flow (6 tests)
- Pre-auth & capture (7 tests)
- Refunds (10 tests)
- Webhooks (12 tests)
- Error scenarios (14 tests)

**When to read:** When writing or running tests

---

### 5. CHANGELOG.md
**What's inside:**
- Version history
- Release notes
- Breaking changes
- New features

**When to read:** Before upgrading versions

---

## 🚀 Quick Navigation

### I want to...

**Get started quickly**
→ [QUICKSTART.md](QUICKSTART.md)

**Understand the complete API**
→ [README.md](README.md)

**See real-world examples**
→ [EXAMPLES.md](EXAMPLES.md)

**Implement a hotel booking system**
→ [EXAMPLES.md - Scenario 2](EXAMPLES.md#2-hotel-reservation-with-pre-authorization)

**Handle webhooks securely**
→ [EXAMPLES.md - Scenario 7](EXAMPLES.md#7-webhook-handler)

**Process refunds**
→ [EXAMPLES.md - Scenario 6](EXAMPLES.md#6-refund-processing)

**Write tests**
→ [tests/README.md](tests/README.md)

**Check what's new**
→ [CHANGELOG.md](CHANGELOG.md)

---

## 📦 File Structure

```
mita-gpg-sdk/
├── README.md                   # Main documentation (12KB)
├── QUICKSTART.md              # Quick start guide (7KB)
├── EXAMPLES.md                # Usage examples (48KB)
├── CHANGELOG.md               # Version history (2KB)
├── DOCUMENTATION.md           # This file
├── composer.json              # Package configuration
├── LICENSE                    # MIT License
├── demo.php                   # Interactive demo script
├── .env.example               # Environment variables
│
├── src/                       # Source code
│   ├── Client/
│   │   └── GpgClient.php      # Main API client
│   ├── DTO/
│   │   ├── PaymentRequest.php
│   │   ├── PaymentResponse.php
│   │   ├── TransactionRequest.php
│   │   └── WebhookPayload.php
│   ├── Enums/
│   │   ├── TransactionType.php
│   │   └── TransactionStatus.php
│   └── Exceptions/
│       ├── GpgException.php
│       ├── AuthenticationException.php
│       ├── ValidationException.php
│       ├── ApiException.php
│       ├── NetworkException.php
│       └── InvalidSignatureException.php
│
└── tests/                     # Test suite
    ├── README.md              # Test documentation (10KB)
    ├── GpgClientTest.php      # Unit tests
    ├── E2E/                   # E2E tests (63 tests total)
    │   ├── PaymentFlowTest.php
    │   ├── PreAuthCaptureFlowTest.php
    │   ├── RefundFlowTest.php
    │   ├── WebhookProcessingTest.php
    │   └── ErrorScenariosTest.php
    ├── Fixtures/
    │   └── ApiResponses.php   # Mock API responses
    └── Helpers/
        └── MockHttpClient.php  # HTTP mocking utility
```

---

## 🔗 External Resources

- **Malta GPG API Documentation**: https://gpgapi.redoc.ly/
- **MITA Website**: https://mita.gov.mt/
- **GPG Information**: https://mita.gov.mt/portfolio/information-systems/government-payment-gateway/

## 📞 Support

- **GPG Support**: support@apcopay.com
- **MITA Contact**: cmd.mita@gov.mt / +356 21234710
- **Package Issues**: Create an issue on GitHub

---

## 📊 Documentation Statistics

| Document | Size | Lines | Purpose |
|----------|------|-------|---------|
| README.md | 12KB | ~450 | Complete SDK reference |
| QUICKSTART.md | 7KB | ~280 | Quick start guide |
| EXAMPLES.md | 48KB | ~1,800 | Real-world examples |
| tests/README.md | 10KB | ~410 | Test documentation |
| CHANGELOG.md | 2KB | ~80 | Version history |
| **Total** | **79KB** | **~3,020** | **Complete coverage** |

---

**All documentation is up-to-date with SDK v1.0.0**