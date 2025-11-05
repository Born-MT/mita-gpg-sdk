# Changelog

All notable changes to the Malta GPG SDK will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2025-01-05

### Added
- Initial release of Malta GPG SDK
- Complete API client for Malta Government Payment Gateway
- Support for all transaction types (SALE, AUTH, CAPTURE, REFUND, VOID)
- Comprehensive DTO classes for type-safe requests and responses
- Webhook signature verification with HMAC SHA256
- Full exception handling with specific exception types
- PHP 8.1+ enums for transaction types and statuses
- Test mode support for sandbox testing
- PHPUnit test suite
- Comprehensive documentation with real-world examples
- Quick start guide
- MIT License

### API Coverage
- `POST /api/HostedPaymentPage` - Create payment
- `PUT /api/Transaction` - Capture, refund, void transactions
- `GET /api/Transaction/{id}` - Get transaction details
- `POST /api/Transactions/GetTransactions` - Get transaction list
- Webhook handling and signature verification

### DTOs
- `PaymentRequest` - Payment creation with fluent interface
- `PaymentResponse` - API response parsing
- `TransactionRequest` - Transaction operations
- `WebhookPayload` - Webhook data parsing

### Enums
- `TransactionType` - SALE, AUTH, CAPTURE, REFUND, VOID
- `TransactionStatus` - PENDING, PROCESSED, DECLINED, etc.

### Exceptions
- `GpgException` - Base exception
- `AuthenticationException` - 401 errors
- `ValidationException` - 400/422 errors
- `ApiException` - General API errors
- `NetworkException` - Connection errors
- `InvalidSignatureException` - Webhook verification failures

### Documentation
- Complete README with API reference
- EXAMPLES.md with 10 real-world scenarios
- QUICKSTART.md for 5-minute setup
- Inline code documentation
- PHPUnit test examples

[Unreleased]: https://github.com/born-mt/mita-gpg-sdk/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/born-mt/mita-gpg-sdk/releases/tag/v1.0.0