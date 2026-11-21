# ADR-0002: Database per Service

- Status: Accepted
- Date: 2026-11-09

## Decision

هر سرویس مالک Database خود است و فقط با Credential مختص خود به آن متصل می‌شود. ارتباط داده‌ای بین سرویس‌ها از طریق API یا Event انجام می‌شود؛ نه Query مستقیم به دیتابیس سرویس دیگر.

## Development compromise

در محیط Development می‌توان یک PostgreSQL Server محلی داشت، اما برای هر سرویس Database و Login Role مستقل می‌سازیم و دسترسی CONNECT عمومی را از Databaseها می‌گیریم. این جداسازی منطقی است و جایگزین Instance مستقل در Production با نیازهای امنیتی/عملیاتی بالاتر نیست.
