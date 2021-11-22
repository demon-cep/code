<?php
/**
 * Отмена купона
 *
 * @api {post} coupon_cancel Отмена купона
 * @apiSampleRequest off
 * @apiName CouponCancel
 * @apiVersion 0.1.0
 * @apiGroup Coupon
 *
 * @apiParam {String} CODE Код купона.
 *
 * @apiSuccess (Success 200) {Array[]} data
 * @apiSuccess (Success 200) {Boolean} data.IS_CANCELED
 *
 */