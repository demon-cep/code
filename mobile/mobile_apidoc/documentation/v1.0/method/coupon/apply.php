<?php
/**
 * Применение купона
 *
 * @api {post} coupon_apply Применение купона
 * @apiSampleRequest off
 * @apiName CouponApply
 * @apiVersion 0.1.0
 * @apiGroup Coupon
 *
 * @apiParam {String} CODE Код купона.
 *
 * @apiSuccess (Success 200) {Array[]} data
 * @apiSuccess (Success 200) {Boolean} data.IS_APPLIED
 *
 */