-- The test suite runs against its own database so it never touches the
-- development data. Parallel runs create their own copies from it.
CREATE DATABASE IF NOT EXISTS testing;
