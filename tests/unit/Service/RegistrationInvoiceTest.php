<?php

/**
 * Larpinq registration invoice
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @category Test
 * @package  OCA\Larpinq\Tests\Unit\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://larpingapp.com
 *
 * @spec openspec/specs/event-registration/spec.md#requirement-invoices-and-receipts-come-from-shillinq-req-rps-006
 */

declare(strict_types=1);

namespace OCA\Larpinq\Tests\Unit\Service;

require_once __DIR__ . '/../Support/PaymentWorld.php';

use OCA\Larpinq\Service\RegistrationInvoice;
use OCA\Larpinq\Tests\Unit\Support\PaymentWorld;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

/**
 * The invoice shillinq issued on request lands on the registration once
 * (REQ-RPS-006): with the request when shillinq issued it at create, else
 * when shillinq reports it on a later change of the request.
 *
 * @covers \OCA\Larpinq\Service\RegistrationInvoice
 * @covers \OCA\Larpinq\Service\PaymentFollowUp
 * @covers \OCA\Larpinq\Service\RegistrationPaymentService
 */
class RegistrationInvoiceTest extends TestCase {

	private const REG = 'e0000000-0000-4000-8000-000000000002';

	private const INVOICE = 'a0000000-0000-4000-8000-000000000077';

	/** @var PaymentWorld The world. */
	private PaymentWorld $world;

	/**
	 * A fresh world.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->world = new PaymentWorld(test: $this);
	}//end setUp()

	/**
	 * An issued invoice gives its id and shillinq's download link for it.
	 *
	 * @return void
	 */
	public function testAnIssuedInvoiceGivesItsIdAndDownloadLink(): void {
		$urls = $this->createMock(IURLGenerator::class);
		$urls->expects($this->once())
			->method('linkToRouteAbsolute')
			->with('shillinq.invoiceApi.pdf', ['invoiceId' => self::INVOICE])
			->willReturn('https://cloud.example.org/index.php/apps/shillinq/api/v1/invoices/' . self::INVOICE . '/pdf');

		$fields = (new RegistrationInvoice(urls: $urls))->fields(registration: [], request: ['invoiceReference' => ' ' . self::INVOICE . ' ']);

		$this->assertSame(
			expected: [
				'invoiceId' => self::INVOICE,
				'invoiceUrl' => 'https://cloud.example.org/index.php/apps/shillinq/api/v1/invoices/' . self::INVOICE . '/pdf',
			],
			actual: $fields
		);
	}//end testAnIssuedInvoiceGivesItsIdAndDownloadLink()

	/**
	 * No invoice on the request, or the one the registration already shows, gives nothing.
	 *
	 * @return void
	 */
	public function testNoNewInvoiceGivesNothing(): void {
		$urls = $this->createMock(IURLGenerator::class);
		$urls->expects($this->never())->method('linkToRouteAbsolute');
		$invoice = new RegistrationInvoice(urls: $urls);

		$this->assertSame(expected: [], actual: $invoice->fields(registration: [], request: []));
		$this->assertSame(expected: [], actual: $invoice->fields(registration: [], request: ['invoiceReference' => '  ']));
		$this->assertSame(expected: [], actual: $invoice->fields(registration: ['invoiceId' => self::INVOICE], request: ['invoiceReference' => self::INVOICE]));
	}//end testNoNewInvoiceGivesNothing()

	/**
	 * Joris asked for an invoice: accepting him stores the invoice shillinq issued with the request.
	 *
	 * @return void
	 */
	public function testTheInvoiceIssuedWithTheRequestLandsOnAcceptance(): void {
		$this->world->seedRegistration(id: self::REG, player: PaymentWorld::SANNE, status: 'pending', more: ['invoiceRequested' => true]);

		$this->world->update(id: self::REG, data: ['status' => 'accepted']);

		$registration = $this->world->registration(id: self::REG);
		$request = array_values($this->world->leaf->requests)[0];
		$this->assertSame(expected: $request['invoiceReference'], actual: $registration['invoiceId']);
		$this->assertSame(expected: 'https://cloud.example.org/shillinq.invoiceApi.pdf/' . $request['invoiceReference'], actual: $registration['invoiceUrl']);
	}//end testTheInvoiceIssuedWithTheRequestLandsOnAcceptance()

	/**
	 * Without an invoice request, acceptance stores no invoice.
	 *
	 * @return void
	 */
	public function testNoInvoiceRequestedStoresNoInvoice(): void {
		$this->world->seedRegistration(id: self::REG, player: PaymentWorld::SANNE, status: 'pending');

		$this->world->update(id: self::REG, data: ['status' => 'accepted']);

		$registration = $this->world->registration(id: self::REG);
		$this->assertSame(expected: 'open', actual: $registration['paymentState']);
		$this->assertArrayNotHasKey(key: 'invoiceId', array: $registration);
		$this->assertArrayNotHasKey(key: 'invoiceUrl', array: $registration);
	}//end testNoInvoiceRequestedStoresNoInvoice()

	/**
	 * An invoice shillinq reports later on an open request lands once, and the payment stays open.
	 *
	 * @return void
	 */
	public function testALaterInvoiceLandsOnceOnAnOpenRegistration(): void {
		$this->world->seedRegistration(id: self::REG, player: PaymentWorld::SANNE, status: 'accepted', more: ['paymentState' => 'open', 'paymentRequestId' => 'req-1']);
		$request = ['id' => 'req-1', 'state' => 'pending', 'invoiceReference' => self::INVOICE];

		$this->world->followUp()->requestChanged(request: $request);
		$first = $this->world->registration(id: self::REG);
		$this->world->followUp()->requestChanged(request: $request);

		$this->assertSame(expected: 'open', actual: $first['paymentState']);
		$this->assertSame(expected: self::INVOICE, actual: $first['invoiceId']);
		$this->assertSame(expected: 'https://cloud.example.org/shillinq.invoiceApi.pdf/' . self::INVOICE, actual: $first['invoiceUrl']);
		$this->assertSame(expected: $first, actual: $this->world->registration(id: self::REG), message: 'the second report writes nothing');
	}//end testALaterInvoiceLandsOnceOnAnOpenRegistration()

	/**
	 * A capture that carries the invoice marks paid and stores the invoice in the same write.
	 *
	 * @return void
	 */
	public function testACaptureCarryingTheInvoiceStoresItWithThePayment(): void {
		$this->world->seedRegistration(id: self::REG, player: PaymentWorld::SANNE, status: 'accepted', more: ['paymentState' => 'open', 'paymentRequestId' => 'req-1']);

		$this->world->followUp()->requestChanged(request: ['id' => 'req-1', 'state' => 'captured', 'invoiceReference' => self::INVOICE]);

		$registration = $this->world->registration(id: self::REG);
		$this->assertSame(expected: 'paid', actual: $registration['paymentState']);
		$this->assertSame(expected: self::INVOICE, actual: $registration['invoiceId']);
	}//end testACaptureCarryingTheInvoiceStoresItWithThePayment()

	/**
	 * A request no registration points at stores nothing.
	 *
	 * @return void
	 */
	public function testAnInvoiceOnAnUnknownRequestStoresNothing(): void {
		$this->world->seedRegistration(id: self::REG, player: PaymentWorld::SANNE, status: 'accepted', more: ['paymentState' => 'open', 'paymentRequestId' => 'req-1']);

		$this->world->followUp()->requestChanged(request: ['id' => 'req-9', 'state' => 'pending', 'invoiceReference' => self::INVOICE]);

		$this->assertArrayNotHasKey(key: 'invoiceId', array: $this->world->registration(id: self::REG));
	}//end testAnInvoiceOnAnUnknownRequestStoresNothing()
}//end class
