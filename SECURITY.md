# Security Policy

## Reporting a vulnerability

Please report suspected vulnerabilities privately to **dev@payzum.com**.
Do not open a public issue for security reports. We acknowledge reports
within 48 hours and aim to ship a fix or mitigation within 14 days.

## Scope

This package handles payment-related requests and webhook signature
verification. Anything that could weaken signature verification, leak an
API key, or alter amounts in transit is in scope and treated as critical.

## Supported versions

The latest minor release receives security fixes.
