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
  "updated_at" datetime,
  "meta" text
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
  "city" varchar,
  "description" text,
  "merchant_category_code" varchar not null default '0000',
  "logo_url" varchar,
  "allow_tip" tinyint(1) not null default '0',
  "default_amount" numeric,
  "min_amount" numeric,
  "max_amount" numeric,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "merchants_code_unique" on "merchants"("code");
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
CREATE TABLE IF NOT EXISTS "settings"(
  "id" integer primary key autoincrement not null,
  "group" varchar not null,
  "name" varchar not null,
  "locked" tinyint(1) not null default '0',
  "payload" text not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "settings_group_name_unique" on "settings"(
  "group",
  "name"
);
CREATE TABLE IF NOT EXISTS "statuses"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "reason" text,
  "model_type" varchar not null,
  "model_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "statuses_model_type_model_id_index" on "statuses"(
  "model_type",
  "model_id"
);
CREATE TABLE IF NOT EXISTS "tags"(
  "id" integer primary key autoincrement not null,
  "name" text not null,
  "slug" text not null,
  "type" varchar,
  "order_column" integer,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE TABLE IF NOT EXISTS "taggables"(
  "tag_id" integer not null,
  "taggable_type" varchar not null,
  "taggable_id" integer not null,
  foreign key("tag_id") references "tags"("id") on delete cascade
);
CREATE INDEX "taggables_taggable_type_taggable_id_index" on "taggables"(
  "taggable_type",
  "taggable_id"
);
CREATE UNIQUE INDEX "taggables_tag_id_taggable_id_taggable_type_unique" on "taggables"(
  "tag_id",
  "taggable_id",
  "taggable_type"
);
CREATE TABLE IF NOT EXISTS "campaigns"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "name" varchar not null,
  "slug" varchar not null,
  "description" text,
  "status" varchar not null default 'draft',
  "instructions" text not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE UNIQUE INDEX "campaigns_slug_unique" on "campaigns"("slug");
CREATE TABLE IF NOT EXISTS "campaign_voucher"(
  "id" integer primary key autoincrement not null,
  "campaign_id" integer not null,
  "voucher_id" integer not null,
  "instructions_snapshot" text not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("campaign_id") references "campaigns"("id") on delete cascade,
  foreign key("voucher_id") references "vouchers"("id") on delete cascade
);
CREATE UNIQUE INDEX "campaign_voucher_campaign_id_voucher_id_unique" on "campaign_voucher"(
  "campaign_id",
  "voucher_id"
);
CREATE TABLE IF NOT EXISTS "permissions"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "guard_name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "permissions_name_guard_name_unique" on "permissions"(
  "name",
  "guard_name"
);
CREATE TABLE IF NOT EXISTS "roles"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "guard_name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "roles_name_guard_name_unique" on "roles"(
  "name",
  "guard_name"
);
CREATE TABLE IF NOT EXISTS "model_has_permissions"(
  "permission_id" integer not null,
  "model_type" varchar not null,
  "model_id" integer not null,
  foreign key("permission_id") references "permissions"("id") on delete cascade,
  primary key("permission_id", "model_id", "model_type")
);
CREATE INDEX "model_has_permissions_model_id_model_type_index" on "model_has_permissions"(
  "model_id",
  "model_type"
);
CREATE TABLE IF NOT EXISTS "model_has_roles"(
  "role_id" integer not null,
  "model_type" varchar not null,
  "model_id" integer not null,
  foreign key("role_id") references "roles"("id") on delete cascade,
  primary key("role_id", "model_id", "model_type")
);
CREATE INDEX "model_has_roles_model_id_model_type_index" on "model_has_roles"(
  "model_id",
  "model_type"
);
CREATE TABLE IF NOT EXISTS "role_has_permissions"(
  "permission_id" integer not null,
  "role_id" integer not null,
  foreign key("permission_id") references "permissions"("id") on delete cascade,
  foreign key("role_id") references "roles"("id") on delete cascade,
  primary key("permission_id", "role_id")
);
CREATE TABLE IF NOT EXISTS "user_voucher"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "voucher_code" varchar not null,
  "generated_at" datetime not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("voucher_code") references "vouchers"("code") on delete cascade
);
CREATE UNIQUE INDEX "user_voucher_user_id_voucher_code_unique" on "user_voucher"(
  "user_id",
  "voucher_code"
);
CREATE INDEX "user_voucher_voucher_code_index" on "user_voucher"(
  "voucher_code"
);
CREATE TABLE IF NOT EXISTS "instruction_items"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "index" varchar not null,
  "type" varchar not null,
  "price" integer not null default '0',
  "currency" varchar not null default 'PHP',
  "meta" text,
  "created_at" datetime,
  "updated_at" datetime,
  "revenue_destination_type" varchar,
  "revenue_destination_id" integer
);
CREATE INDEX "instruction_items_type_index" on "instruction_items"("type");
CREATE UNIQUE INDEX "instruction_items_index_unique" on "instruction_items"(
  "index"
);
CREATE TABLE IF NOT EXISTS "instruction_item_price_history"(
  "id" integer primary key autoincrement not null,
  "instruction_item_id" integer not null,
  "old_price" integer not null,
  "new_price" integer not null,
  "currency" varchar not null default 'PHP',
  "changed_by" integer,
  "reason" text,
  "effective_at" datetime not null default CURRENT_TIMESTAMP,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("instruction_item_id") references "instruction_items"("id") on delete cascade,
  foreign key("changed_by") references "users"("id") on delete set null
);
CREATE TABLE IF NOT EXISTS "voucher_generation_charges"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "campaign_id" integer,
  "voucher_codes" text not null,
  "voucher_count" integer not null,
  "instructions_snapshot" text not null,
  "charge_breakdown" text not null,
  "total_charge" numeric not null,
  "charge_per_voucher" numeric not null,
  "generated_at" datetime not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("campaign_id") references "campaigns"("id") on delete set null
);
CREATE INDEX "voucher_generation_charges_user_id_generated_at_index" on "voucher_generation_charges"(
  "user_id",
  "generated_at"
);
CREATE INDEX "voucher_generation_charges_campaign_id_index" on "voucher_generation_charges"(
  "campaign_id"
);
CREATE TABLE IF NOT EXISTS "notifications"(
  "id" varchar not null,
  "type" varchar not null,
  "notifiable_type" varchar not null,
  "notifiable_id" integer not null,
  "data" text not null,
  "read_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  primary key("id")
);
CREATE INDEX "notifications_notifiable_type_notifiable_id_index" on "notifications"(
  "notifiable_type",
  "notifiable_id"
);
CREATE TABLE IF NOT EXISTS "account_balances"(
  "id" integer primary key autoincrement not null,
  "account_number" varchar not null,
  "gateway" varchar not null default 'netbank',
  "balance" integer not null default '0',
  "available_balance" integer not null default '0',
  "currency" varchar not null default 'PHP',
  "checked_at" datetime not null,
  "metadata" text,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "account_balances_account_number_gateway_unique" on "account_balances"(
  "account_number",
  "gateway"
);
CREATE INDEX "account_balances_account_number_index" on "account_balances"(
  "account_number"
);
CREATE INDEX "account_balances_checked_at_index" on "account_balances"(
  "checked_at"
);
CREATE TABLE IF NOT EXISTS "balance_history"(
  "id" integer primary key autoincrement not null,
  "account_number" varchar not null,
  "gateway" varchar not null,
  "balance" integer not null,
  "available_balance" integer not null,
  "currency" varchar not null default 'PHP',
  "recorded_at" datetime not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "balance_history_account_number_index" on "balance_history"(
  "account_number"
);
CREATE INDEX "balance_history_recorded_at_index" on "balance_history"(
  "recorded_at"
);
CREATE TABLE IF NOT EXISTS "balance_alerts"(
  "id" integer primary key autoincrement not null,
  "account_number" varchar not null,
  "gateway" varchar not null default 'netbank',
  "threshold" integer not null,
  "alert_type" varchar not null,
  "recipients" text not null,
  "enabled" tinyint(1) not null default '1',
  "last_triggered_at" datetime,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "balance_alerts_account_number_index" on "balance_alerts"(
  "account_number"
);
CREATE INDEX "instruction_items_revenue_destination_index" on "instruction_items"(
  "revenue_destination_type",
  "revenue_destination_id"
);
CREATE TABLE IF NOT EXISTS "revenue_collections"(
  "id" integer primary key autoincrement not null,
  "instruction_item_id" integer not null,
  "collected_by_user_id" integer not null,
  "destination_type" varchar not null,
  "destination_id" integer not null,
  "amount" integer not null,
  "transfer_uuid" varchar not null,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("instruction_item_id") references "instruction_items"("id") on delete cascade,
  foreign key("collected_by_user_id") references "users"("id"),
  foreign key("transfer_uuid") references "transfers"("uuid")
);
CREATE INDEX "revenue_collections_destination_type_destination_id_index" on "revenue_collections"(
  "destination_type",
  "destination_id"
);
CREATE INDEX "revenue_collections_collected_by_user_id_index" on "revenue_collections"(
  "collected_by_user_id"
);
CREATE INDEX "revenue_collections_transfer_uuid_index" on "revenue_collections"(
  "transfer_uuid"
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
INSERT INTO migrations VALUES(23,'2025_11_08_115914_add_meta_to_users_table',1);
INSERT INTO migrations VALUES(24,'2025_11_08_124447_create_settings_table',1);
INSERT INTO migrations VALUES(25,'2025_11_09_003637_create_statuses_table',1);
INSERT INTO migrations VALUES(26,'2025_11_09_004141_create_tag_tables',1);
INSERT INTO migrations VALUES(27,'2025_11_09_120248_create_campaigns_table',1);
INSERT INTO migrations VALUES(28,'2025_11_09_121515_create_campaign_voucher_table',1);
INSERT INTO migrations VALUES(29,'2025_11_10_151638_create_permission_tables',1);
INSERT INTO migrations VALUES(30,'2025_11_10_151645_create_user_voucher_table',1);
INSERT INTO migrations VALUES(31,'2025_11_10_151652_create_instruction_items_table',1);
INSERT INTO migrations VALUES(32,'2025_11_10_151659_create_instruction_item_price_history_table',1);
INSERT INTO migrations VALUES(33,'2025_11_10_151706_create_voucher_generation_charges_table',1);
INSERT INTO migrations VALUES(34,'2025_11_13_173143_create_notifications_table',1);
INSERT INTO migrations VALUES(35,'2025_11_14_200952_create_account_balances_table',1);
INSERT INTO migrations VALUES(36,'2025_11_14_201017_create_balance_history_table',1);
INSERT INTO migrations VALUES(37,'2025_11_14_201030_create_balance_alerts_table',1);
INSERT INTO migrations VALUES(38,'2025_11_14_225644_add_revenue_destination_to_instruction_items_table',1);
INSERT INTO migrations VALUES(39,'2025_11_14_225733_create_revenue_collections_table',1);
