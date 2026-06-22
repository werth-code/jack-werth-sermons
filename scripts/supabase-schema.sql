-- Jack Werth sermon library — Supabase schema for accounts, hearts, playlists, resume.
-- Run this once in your Supabase project: Dashboard → SQL Editor → New query → paste → Run.
-- Row-Level Security ensures every user can only ever read/write their OWN data.

-- ---------- tables ----------
create table if not exists profiles (
  id           uuid primary key references auth.users on delete cascade,
  display_name text,
  created_at   timestamptz default now()
);

create table if not exists favorites (
  user_id    uuid not null references auth.users on delete cascade,
  sermon_id  text not null,                 -- archive.org identifier (stable)
  passage    text,
  created_at timestamptz default now(),
  primary key (user_id, sermon_id)
);

create table if not exists playlists (
  id         uuid primary key default gen_random_uuid(),
  user_id    uuid not null references auth.users on delete cascade,
  name       text not null,
  created_at timestamptz default now()
);

create table if not exists playlist_items (
  id          uuid primary key default gen_random_uuid(),
  playlist_id uuid not null references playlists on delete cascade,
  sermon_id   text not null,
  passage     text,
  position    int  not null default 0,
  added_at    timestamptz default now()
);

-- Resume / "pick up where you left off". One row per user per context
-- (context = 'global' for the main player, or 'playlist:<uuid>' for a playlist).
create table if not exists playback_state (
  user_id          uuid not null references auth.users on delete cascade,
  context          text not null default 'global',
  sermon_id        text,
  queue_index      int  default 0,
  position_seconds numeric default 0,
  updated_at       timestamptz default now(),
  primary key (user_id, context)
);

-- ---------- row-level security ----------
alter table profiles       enable row level security;
alter table favorites      enable row level security;
alter table playlists      enable row level security;
alter table playlist_items enable row level security;
alter table playback_state enable row level security;

create policy "own_profile"        on profiles       for all using (auth.uid() = id)      with check (auth.uid() = id);
create policy "own_favorites"      on favorites      for all using (auth.uid() = user_id) with check (auth.uid() = user_id);
create policy "own_playlists"      on playlists      for all using (auth.uid() = user_id) with check (auth.uid() = user_id);
create policy "own_playback_state" on playback_state for all using (auth.uid() = user_id) with check (auth.uid() = user_id);
create policy "own_playlist_items" on playlist_items for all
  using     (exists (select 1 from playlists p where p.id = playlist_id and p.user_id = auth.uid()))
  with check (exists (select 1 from playlists p where p.id = playlist_id and p.user_id = auth.uid()));

-- ---------- auto-create a profile row on signup ----------
create or replace function public.handle_new_user() returns trigger
  language plpgsql security definer set search_path = public as $$
begin
  insert into public.profiles (id, display_name)
  values (new.id, coalesce(new.raw_user_meta_data->>'display_name', split_part(new.email,'@',1)))
  on conflict (id) do nothing;
  return new;
end; $$;

drop trigger if exists on_auth_user_created on auth.users;
create trigger on_auth_user_created after insert on auth.users
  for each row execute function public.handle_new_user();
