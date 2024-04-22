class lineManager:
    def __init__(self, grid):
        self.grid = grid
        self.lines_cleared = 0
        self.score = 0

    def check_and_clear_lines(self):
        """Checks each row in the grid for full lines, clears them, and counts them."""
        new_grid = [row for row in self.grid if not all(cell != 0 for cell in row)]
        self.lines_cleared = len(self.grid) - len(new_grid)

        # Add empty lines at the top of the grid for each line cleared
        for _ in range(self.lines_cleared):
            new_grid.insert(0, [0] * len(self.grid[0]))

        self.grid = new_grid
        self.update_score()

    def update_score(self):
        """Update score based on lines cleared."""
        self.score += self.lines_cleared ** 2 * 100

    def get_score(self):
        """Returns the current score."""
        return self.score

    def get_lines_cleared(self):
        """Returns the total number of lines cleared."""
        return self.lines_cleared
