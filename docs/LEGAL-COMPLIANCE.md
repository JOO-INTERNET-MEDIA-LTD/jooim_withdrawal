# Legal implementation notes

## EU consumer withdrawal

The module is designed around Directive 2011/83/EU on consumer rights as amended by Directive (EU) 2023/2673. The 2023 amendment introduces Article 11a for distance contracts concluded through an online interface. The relevant rules apply from 19 June 2026.

For applicable contracts, the implementation provides:

- a prominent withdrawal entry linked from the footer; the entry page also exposes the direct withdrawal function without requiring access to order data;
- collection/confirmation of the consumer's name;
- identification of the contract/order;
- an electronic address to which the acknowledgement is sent;
- a separate review step;
- an unambiguous final `Confirm withdrawal` action;
- immediate on-screen confirmation;
- acknowledgement on a durable medium by email, including the submitted content, date and time;
- an immutable server-side snapshot and hash for evidence/audit.

The secure magic-link flow is an additional convenience that can expose order items after verification. It is not the sole legal path because access to an old mailbox should not be an unnecessary prerequisite for exercising the online withdrawal function.

The module never uses order reference alone as authentication and does not disclose order/customer data before authentication.

The low anti-enumeration rate limits apply to the optional order-reference/magic-link discovery flow. They do not block the direct Article 11a withdrawal function, because a low shared-IP threshold could otherwise prevent a consumer from exercising the online withdrawal right during the statutory period. The direct flow remains protected by CSRF, server-side validation, idempotency, audit logging and pseudonymous abuse telemetry.

## Withdrawal period

The built-in period evaluation is advisory. It uses a known delivery event when available and never blocks submission merely because the result is `possibly_expired` or `unknown`. Exceptions (personalised goods, sealed hygiene goods, digital content, services, perishables and other special cases) require legal assessment and must not be decided solely by catalogue flags.

## GDPR

The withdrawal form does not force a consent checkbox for processing that is necessary to receive, record and handle the withdrawal. GDPR Article 6 provides legal bases other than consent, including processing necessary for contract performance and processing necessary for compliance with a legal obligation. The actual legal basis and retention period remain the merchant/controller's responsibility and must be reflected in its privacy notice.

The module minimises public disclosure, stores only HMAC/pseudonymous IP data for short-term anti-abuse controls, avoids raw IP in the legal snapshot, and stores a version/hash of the displayed privacy information in the submission snapshot.

## Accessibility

The direct withdrawal flow uses semantic headings, fieldsets, legends, explicit form labels and a server-side flow that does not require JavaScript to submit. This is accessibility-oriented implementation, not a certification of compliance with every national implementation of the European Accessibility Act.

## Official sources

- EUR-Lex: Directive (EU) 2023/2673 — https://eur-lex.europa.eu/eli/dir/2023/2673/oj/eng
- European Commission: Consumer Rights Directive — https://commission.europa.eu/law/law-topic/consumer-protection-law/consumer-contract-law/consumer-rights-directive_en
- EUR-Lex: Regulation (EU) 2016/679 (GDPR), Article 6 — https://eur-lex.europa.eu/eli/reg/2016/679/oj
- EDPB: Legal basis — https://www.edpb.europa.eu/topics/key-gdpr-concepts/legal-basis_en

This document is implementation guidance, not a substitute for legal advice in each country/sector.
