CREATE TABLE IF NOT EXISTS "migrations"(
  "id" integer primary key autoincrement not null,
  "migration" varchar not null,
  "batch" integer not null
);
CREATE TABLE IF NOT EXISTS "users"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "email" varchar not null,
  "email_verified_at" datetime,
  "workos_id" varchar not null,
  "remember_token" varchar,
  "avatar" text not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "users_email_unique" on "users"("email");
CREATE UNIQUE INDEX "users_workos_id_unique" on "users"("workos_id");
CREATE TABLE IF NOT EXISTS "sessions"(
  "id" varchar not null,
  "user_id" integer,
  "ip_address" varchar,
  "user_agent" text,
  "payload" text not null,
  "last_activity" integer not null,
  primary key("id")
);
CREATE INDEX "sessions_user_id_index" on "sessions"("user_id");
CREATE INDEX "sessions_last_activity_index" on "sessions"("last_activity");
CREATE TABLE IF NOT EXISTS "cache"(
  "key" varchar not null,
  "value" text not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE TABLE IF NOT EXISTS "cache_locks"(
  "key" varchar not null,
  "owner" varchar not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE TABLE IF NOT EXISTS "jobs"(
  "id" integer primary key autoincrement not null,
  "queue" varchar not null,
  "payload" text not null,
  "attempts" integer not null,
  "reserved_at" integer,
  "available_at" integer not null,
  "created_at" integer not null
);
CREATE INDEX "jobs_queue_index" on "jobs"("queue");
CREATE TABLE IF NOT EXISTS "job_batches"(
  "id" varchar not null,
  "name" varchar not null,
  "total_jobs" integer not null,
  "pending_jobs" integer not null,
  "failed_jobs" integer not null,
  "failed_job_ids" text not null,
  "options" text,
  "cancelled_at" integer,
  "created_at" integer not null,
  "finished_at" integer,
  primary key("id")
);
CREATE TABLE IF NOT EXISTS "failed_jobs"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "connection" text not null,
  "queue" text not null,
  "payload" text not null,
  "exception" text not null,
  "failed_at" datetime not null default CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs"("uuid");
CREATE TABLE IF NOT EXISTS "vouchers"(
  "id" integer primary key autoincrement not null,
  "code" varchar not null,
  "owner_type" varchar,
  "owner_id" integer,
  "metadata" text,
  "starts_at" datetime,
  "expires_at" datetime,
  "redeemed_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "processed_on" datetime
);
CREATE INDEX "vouchers_owner_type_owner_id_index" on "vouchers"(
  "owner_type",
  "owner_id"
);
CREATE UNIQUE INDEX "vouchers_code_unique" on "vouchers"("code");
CREATE INDEX "vouchers_starts_at_index" on "vouchers"("starts_at");
CREATE INDEX "vouchers_expires_at_index" on "vouchers"("expires_at");
CREATE INDEX "vouchers_redeemed_at_index" on "vouchers"("redeemed_at");
CREATE TABLE IF NOT EXISTS "redeemers"(
  "id" integer primary key autoincrement not null,
  "voucher_id" integer not null,
  "redeemer_type" varchar not null,
  "redeemer_id" integer not null,
  "metadata" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("voucher_id") references "vouchers"("id") on delete cascade
);
CREATE INDEX "redeemers_redeemer_type_redeemer_id_index" on "redeemers"(
  "redeemer_type",
  "redeemer_id"
);
CREATE TABLE IF NOT EXISTS "voucher_entity"(
  "id" integer primary key autoincrement not null,
  "voucher_id" integer not null,
  "entity_type" varchar not null,
  "entity_id" integer not null,
  foreign key("voucher_id") references "vouchers"("id") on delete cascade
);
CREATE INDEX "voucher_entity_entity_type_entity_id_index" on "voucher_entity"(
  "entity_type",
  "entity_id"
);
CREATE UNIQUE INDEX "entity" on "voucher_entity"(
  "voucher_id",
  "entity_type",
  "entity_id"
);
CREATE TABLE IF NOT EXISTS "money_issuers"(
  "id" integer primary key autoincrement not null,
  "code" varchar not null,
  "name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "money_issuers_code_unique" on "money_issuers"("code");
CREATE UNIQUE INDEX "money_issuers_name_unique" on "money_issuers"("name");
CREATE TABLE IF NOT EXISTS "merchants"(
  "id" integer primary key autoincrement not null,
  "code" varchar not null,
  "name" varchar not null,
  "city" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "merchants_code_unique" on "merchants"("code");
CREATE UNIQUE INDEX "merchants_name_unique" on "merchants"("name");
CREATE TABLE IF NOT EXISTS "transfers"(
  "id" integer primary key autoincrement not null,
  "from_id" integer not null,
  "to_id" integer not null,
  "status" varchar check("status" in('exchange', 'transfer', 'paid', 'refund', 'gift')) not null default 'transfer',
  "status_last" varchar check("status_last" in('exchange', 'transfer', 'paid', 'refund', 'gift')),
  "deposit_id" integer not null,
  "withdraw_id" integer not null,
  "discount" numeric not null default '0',
  "fee" numeric not null default '0',
  "uuid" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  "extra" text,
  foreign key("deposit_id") references "transactions"("id") on delete cascade,
  foreign key("withdraw_id") references "transactions"("id") on delete cascade
);
CREATE UNIQUE INDEX "transfers_uuid_unique" on "transfers"("uuid");
CREATE TABLE IF NOT EXISTS "wallets"(
  "id" integer primary key autoincrement not null,
  "holder_type" varchar not null,
  "holder_id" integer not null,
  "name" varchar not null,
  "slug" varchar not null,
  "uuid" varchar not null,
  "description" varchar,
  "meta" text,
  "balance" numeric not null default '0',
  "decimal_places" integer not null default '2',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime
);
CREATE INDEX "wallets_holder_type_holder_id_index" on "wallets"(
  "holder_type",
  "holder_id"
);
CREATE UNIQUE INDEX "wallets_holder_type_holder_id_slug_unique" on "wallets"(
  "holder_type",
  "holder_id",
  "slug"
);
CREATE INDEX "wallets_slug_index" on "wallets"("slug");
CREATE UNIQUE INDEX "wallets_uuid_unique" on "wallets"("uuid");
CREATE TABLE IF NOT EXISTS "transactions"(
  "id" integer primary key autoincrement not null,
  "payable_type" varchar not null,
  "payable_id" integer not null,
  "wallet_id" integer not null,
  "type" varchar not null,
  "amount" numeric not null,
  "confirmed" tinyint(1) not null,
  "meta" text,
  "uuid" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("wallet_id") references "wallets"("id") on delete cascade
);
CREATE INDEX "payable_confirmed_ind" on "transactions"(
  "payable_type",
  "payable_id",
  "confirmed"
);
CREATE INDEX "payable_type_confirmed_ind" on "transactions"(
  "payable_type",
  "payable_id",
  "type",
  "confirmed"
);
CREATE INDEX "payable_type_ind" on "transactions"(
  "payable_type",
  "payable_id",
  "type"
);
CREATE INDEX "payable_type_payable_id_ind" on "transactions"(
  "payable_type",
  "payable_id"
);
CREATE INDEX "transactions_payable_type_payable_id_index" on "transactions"(
  "payable_type",
  "payable_id"
);
CREATE INDEX "transactions_type_index" on "transactions"("type");
CREATE UNIQUE INDEX "transactions_uuid_unique" on "transactions"("uuid");
CREATE INDEX "transfers_from_id_index" on "transfers"("from_id");
CREATE INDEX "transfers_to_id_index" on "transfers"("to_id");
CREATE TABLE IF NOT EXISTS "merchant_user"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "merchant_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("merchant_id") references "merchants"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "channels"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "value" varchar not null,
  "model_type" varchar not null,
  "model_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "channels_model_type_model_id_index" on "channels"(
  "model_type",
  "model_id"
);
CREATE TABLE IF NOT EXISTS "contacts"(
  "id" integer primary key autoincrement not null,
  "mobile" varchar not null,
  "country" varchar not null,
  "bank_account" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "meta" text
);
CREATE TABLE IF NOT EXISTS "inputs"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "value" text not null,
  "model_type" varchar not null,
  "model_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "inputs_model_type_model_id_index" on "inputs"(
  "model_type",
  "model_id"
);
CREATE TABLE IF NOT EXISTS "sms"(
  "id" integer primary key autoincrement not null,
  "from" varchar not null,
  "to" varchar not null,
  "message" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "sms_from_index" on "sms"("from");
CREATE INDEX "sms_to_index" on "sms"("to");
CREATE INDEX "sms_message_index" on "sms"("message");
CREATE TABLE IF NOT EXISTS "cash"(
  "id" integer primary key autoincrement not null,
  "amount" integer not null,
  "currency" varchar not null default 'PHP',
  "reference_type" varchar,
  "reference_id" integer,
  "meta" text,
  "secret" varchar,
  "expires_on" datetime,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "cash_reference_index" on "cash"(
  "reference_type",
  "reference_id"
);
CREATE TABLE IF NOT EXISTS "personal_access_tokens"(
  "id" integer primary key autoincrement not null,
  "tokenable_type" varchar not null,
  "tokenable_id" integer not null,
  "name" text not null,
  "token" varchar not null,
  "abilities" text,
  "last_used_at" datetime,
  "expires_at" datetime,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "personal_access_tokens_tokenable_type_tokenable_id_index" on "personal_access_tokens"(
  "tokenable_type",
  "tokenable_id"
);
CREATE UNIQUE INDEX "personal_access_tokens_token_unique" on "personal_access_tokens"(
  "token"
);
CREATE INDEX "personal_access_tokens_expires_at_index" on "personal_access_tokens"(
  "expires_at"
);

INSERT INTO migrations VALUES(1,'0001_01_01_000000_create_users_table',1);
INSERT INTO migrations VALUES(2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO migrations VALUES(3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO migrations VALUES(4,'0002_01_01_000000_create_voucher_tables',1);
INSERT INTO migrations VALUES(5,'0003_01_01_000000_create_money_issuers_table',1);
INSERT INTO migrations VALUES(6,'1999_03_17_000000_create_merchants_table',1);
INSERT INTO migrations VALUES(7,'2018_11_06_222923_create_transactions_table',1);
INSERT INTO migrations VALUES(8,'2018_11_07_192923_create_transfers_table',1);
INSERT INTO migrations VALUES(9,'2018_11_15_124230_create_wallets_table',1);
INSERT INTO migrations VALUES(10,'2021_11_02_202021_update_wallets_uuid_table',1);
INSERT INTO migrations VALUES(11,'2023_12_30_113122_extra_columns_removed',1);
INSERT INTO migrations VALUES(12,'2023_12_30_204610_soft_delete',1);
INSERT INTO migrations VALUES(13,'2024_01_24_185401_add_extra_column_in_transfer',1);
INSERT INTO migrations VALUES(14,'2024_03_17_000000_create_merchant_user_table',1);
INSERT INTO migrations VALUES(15,'2024_08_02_000000_create_channels_table',1);
INSERT INTO migrations VALUES(16,'2024_08_02_000000_create_contacts_table',1);
INSERT INTO migrations VALUES(17,'2024_08_02_000000_create_inputs_table',1);
INSERT INTO migrations VALUES(18,'2024_08_02_000000_create_sms_table',1);
INSERT INTO migrations VALUES(19,'2024_08_02_202500_create_cash_table',1);
INSERT INTO migrations VALUES(20,'2024_08_26_202500_add_processed_on_to_vouchers_table',1);
INSERT INTO migrations VALUES(21,'2025_08_01_123520_add_meta_to_contacts_table',1);
INSERT INTO migrations VALUES(22,'2025_11_08_014153_create_personal_access_tokens_table',1);
